<?php
/**
 * Optimized SalesLayer Test Runner
 *
 * Features:
 * - Object-oriented architecture
 * - Consistent error handling
 * - Robust argument validation
 * - Elimination of duplicate code
 * - Modular and extensible commands
 *
 * @author Peter
 * @version 3.0 - Optimized
 */

/**
 * Configuration for test runner
 */
class RunnerConfig {
    const DEFAULT_WAIT_MINUTES = 10;
    const LOG_DATE_FORMAT = 'Y-m-d';

    public static function getLogPath(): string {
        return "tests/logs/autotest_" . date(self::LOG_DATE_FORMAT) . ".log";
    }

    public static function getWordPressPath(): string {
        return dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';
    }
}

/**
 * Command line argument validator
 */
class ArgumentValidator {
    public static function validate(array $argv): array {
        $command = $argv[1] ?? 'help';
        $waitMinutes = isset($argv[2]) ? filter_var($argv[2], FILTER_VALIDATE_INT) : RunnerConfig::DEFAULT_WAIT_MINUTES;
        $options = array_slice($argv, 3);

        // Validate that wait_minutes is a valid number
        if ($waitMinutes === false || $waitMinutes < 1) {
            $waitMinutes = RunnerConfig::DEFAULT_WAIT_MINUTES;
        }

        return compact('command', 'waitMinutes', 'options');
    }
}

/**
 * Centralized error handler
 */
class ErrorHandler {
    public static function exitWithError(string $message, int $code = 1): void {
        echo "ERROR: {$message}\n";
        echo "Log: " . RunnerConfig::getLogPath() . "\n";
        exit($code);
    }

    public static function showResult(bool $success, string $successMsg, string $errorMsg = "", array $extraData = []): void {
        if ($success) {
            echo $successMsg . "\n";
            foreach ($extraData as $key => $value) {
                echo "{$key}: {$value}\n";
            }
            exit(0);
        } else {
            echo ($errorMsg ?: "OPERATION FAILED") . "\n";
            foreach ($extraData as $key => $value) {
                echo "{$key}: {$value}\n";
            }
            echo "See details in log\n";
            exit(1);
        }
    }
}

/**
 * Interface for testing system commands
 */
interface CommandInterface {
    public function execute(SalesLayerAutoTest $tester, array $args): void;
}

/**
 * Base command for simple operations
 */
class SimpleCommand implements CommandInterface {
    private string $method;
    private string $actionMessage;

    public function __construct(string $method, string $actionMessage) {
        $this->method = $method;
        $this->actionMessage = $actionMessage;
    }

    public function execute(SalesLayerAutoTest $tester, array $args): void {
        echo $this->actionMessage . "\n";
        $result = $tester->{$this->method}();
        ErrorHandler::showResult($result !== false, "OPERATION SUCCESSFUL", "OPERATION ERROR");
    }
}

/**
 * Command for operations that return numeric data
 */
class CountCommand implements CommandInterface {
    private string $method;
    private string $actionMessage;
    private string $label;

    public function __construct(string $method, string $actionMessage, string $label) {
        $this->method = $method;
        $this->actionMessage = $actionMessage;
        $this->label = $label;
    }

    public function execute(SalesLayerAutoTest $tester, array $args): void {
        echo "🔄 {$this->actionMessage}\n";
        $result = $tester->{$this->method}();

        // Si el resultado es un array, mostrar múltiples valores
        if (is_array($result)) {
            $details = [];
            foreach ($result as $key => $value) {
                $details[ucfirst($key)] = number_format($value);
            }
            ErrorHandler::showResult(true, "COUNTS RETRIEVED", "", $details);
        } else if ($result !== false) {
            // Compatibilidad con métodos que devuelven un solo valor
            ErrorHandler::showResult(true, $this->label . ": " . number_format($result));
        } else {
            ErrorHandler::showResult(false, "", "ERROR GETTING " . strtoupper($this->label));
        }
    }
}

/**
 * Command for main test (run)
 */
class RunCommand implements CommandInterface {
    public function execute(SalesLayerAutoTest $tester, array $args): void {
        $waitMinutes = $args['waitMinutes'];
        $cleanupAfter = !in_array('no-cleanup', $args['options']);

        echo "Running test ({$waitMinutes} min)...\n";

        $startTime = time();
        $result = $tester->run_complete_test($waitMinutes, $cleanupAfter);
        $totalTime = time() - $startTime;

        if ($result['success']) {
            ErrorHandler::showResult(true, "TEST SUCCESSFUL", "", [
                "Processed" => "{$result['processed_count']} elements",
                "Time" => "{$totalTime}s"
            ]);
        } else {
            ErrorHandler::showResult(false, "TEST FAILED", "", [
                "Inserted" => $result['inserted_count'] ?? 0,
                "Processed" => $result['processed_count'] ?? 0,
                "Time" => "{$totalTime}s"
            ]);
        }
    }
}

/**
 * Command for processed data validation
 */
class ValidateDataCommand implements CommandInterface {
    public function execute(SalesLayerAutoTest $tester, array $args): void {
        echo "Validating processed data...\n";

        $jsonData = $tester->read_mockup_data();
        if (empty($jsonData)) {
            ErrorHandler::showResult(false, "", "ERROR: No reference data available");
        }

        $validationResults = $tester->validateProcessedData($jsonData);
        if ($validationResults['success']) {
            ErrorHandler::showResult(true, "VALIDATION SUCCESSFUL", "", [
                "Validated" => "{$validationResults['processed_count']} elements"
            ]);
        } else {
            ErrorHandler::showResult(false, "", "VALIDATION FAILED");
        }
    }
}

/**
 * Command for processed data cleanup
 */
class CleanupProcessedCommand implements CommandInterface {
    public function execute(SalesLayerAutoTest $tester, array $args): void {
        echo "Removing processed data...\n";

        $jsonData = $tester->read_mockup_data();
        if (empty($jsonData)) {
            ErrorHandler::showResult(false, "", "ERROR: No reference data available");
        }

        $cleanupResults = $tester->cleanupProcessedData($jsonData);
        if ($cleanupResults['success']) {
            ErrorHandler::showResult(true, "CLEANUP SUCCESSFUL", "", [
                "Deleted" => "{$cleanupResults['total_deleted']} elements"
            ]);
        } else {
            ErrorHandler::showResult(false, "CLEANUP WITH ERRORS", "", [
                "Deleted" => "{$cleanupResults['total_deleted']} elements"
            ]);
        }
    }
}

/**
 * Help command
 */
class HelpCommand implements CommandInterface {
    public function execute(SalesLayerAutoTest $tester, array $args): void {
        echo "\nHELP - SALESLAYER AUTOTEST\n";
        echo "===============================\n\n";
        echo "COMMANDS:\n";
        echo "  run [min] [no-cleanup]  Complete test\n";
        echo "  cleanup                 Clean ALL data (⚠️ CAUTION: Deletes everything)\n";
        echo "  cleanup-processed       Remove processed data\n";
        echo "  status                  System status\n";
        echo "  validate                Validate environment\n";
        echo "  validate-data           Validate processed data\n";
        echo "  validate-logs           Validate logs\n";
        echo "  setup-attributes        Configure attributes\n";
        echo "  count                   Records count (total, test, pending)\n";
        echo "  help                    This help\n\n";
        echo "FEATURES:\n";
        echo "  Minimal output in terminal\n";
        echo "  Complete details in log\n";
        echo "  Ideal for CI/CD\n";
        echo "  Standard exit codes\n\n";
        echo "Log: " . RunnerConfig::getLogPath() . "\n";
        exit(0);
    }
}

/**
 * Main class for optimized test runner
 */
class SalesLayerTestRunner {
    private SalesLayerAutoTest $tester;
    private array $commands;
    private array $aliases;

    public function __construct() {
        $this->validateEnvironment();
        $this->loadDependencies();
        $this->initializeCommands();
    }

    /**
     * Validate that environment is ready
     */
    private function validateEnvironment(): void {
        // Check if we're running in CLI mode
        if (php_sapi_name() !== 'cli') {
            ErrorHandler::exitWithError("This script must be run from command line");
        }

        // Check if we're likely in a Docker/WordPress environment
        $wpPath = RunnerConfig::getWordPressPath();
        if (!file_exists($wpPath)) {
            ErrorHandler::exitWithError("Cannot find WordPress at: {$wpPath}");
        }

        // Only try to load WordPress if we're in the right environment
        // Check for WordPress constants file first
        $wpConfigPath = dirname($wpPath) . '/wp-config.php';
        if (!file_exists($wpConfigPath)) {
            ErrorHandler::exitWithError("WordPress configuration not found at: {$wpConfigPath}");
        }

        // Try to load WordPress safely
        try {
            // Set a flag to indicate we're in testing mode
            if (!defined('WP_USE_THEMES')) {
                define('WP_USE_THEMES', false);
            }

            // Suppress output during WordPress load
            ob_start();
            require_once($wpPath);
            ob_end_clean();

        } catch (Exception $e) {
            ErrorHandler::exitWithError("Failed to load WordPress: " . $e->getMessage());
        } catch (Error $e) {
            ErrorHandler::exitWithError("PHP Error loading WordPress: " . $e->getMessage());
        }

        // Validate WordPress is properly loaded
        $checks = [
            'WordPress Functions' => function_exists('wp_get_current_user'),
            'SalesLayer Plugin' => defined('SLYR_WC_syncdata_table'),
            'WordPress Database' => isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb'])
        ];

        foreach ($checks as $name => $result) {
            if (!$result) {
                ErrorHandler::exitWithError("{$name} is not available - ensure WordPress and SalesLayer plugin are properly installed");
            }
        }
    }

    /**
     * Load necessary dependencies
     */
    private function loadDependencies(): void {
        require_once(__DIR__ . '/SalesLayerAutoTest.php');
        $this->tester = new SalesLayerAutoTest();
    }

    /**
     * Initialize available commands
     */
    private function initializeCommands(): void {
        $this->commands = [
            'run' => new RunCommand(),
            'cleanup' => new SimpleCommand('cleanup_test_data', 'Cleaning data...'),
            'status' => new SimpleCommand('show_system_report', 'Generating report...'),
            'validate' => new SimpleCommand('validate_environment', 'Validating environment...'),
            'count' => new CountCommand('get_all_records_count', 'Getting counts...', 'Records'),
            'setup-attributes' => new SimpleCommand('ensure_product_attributes', 'Configuring attributes...'),
            'validate-data' => new ValidateDataCommand(),
            'cleanup-processed' => new CleanupProcessedCommand(),
            'validate-logs' => new SimpleCommand('validate_logs', 'Validating logs...'),
            'help' => new HelpCommand()
        ];

        // Define aliases
        $this->aliases = [
            'test' => 'run',
            'clean' => 'cleanup',
            'validate-environment' => 'validate',
            'attributes' => 'setup-attributes',
            'validate-processed' => 'validate-data',
            'delete-processed' => 'cleanup-processed',
            'logs' => 'validate-logs',
            '--help' => 'help',
            '-h' => 'help'
        ];
    }

    /**
     * Execute command
     */
    public function execute(array $argv): void {
        $args = ArgumentValidator::validate($argv);
        $command = $this->resolveCommand($args['command']);

        echo "SalesLayer Automated Testing\n";
        echo "Detailed log: " . RunnerConfig::getLogPath() . "\n";

        try {
            $this->commands[$command]->execute($this->tester, $args);
        } catch (Exception $e) {
            ErrorHandler::exitWithError($e->getMessage());
        }
    }

    /**
     * Resolve command considering aliases
     */
    private function resolveCommand(string $command): string {
        // Check if it's an alias
        if (isset($this->aliases[$command])) {
            $command = $this->aliases[$command];
        }

        // Check if command exists
        if (!isset($this->commands[$command])) {
            return 'help';
        }

        return $command;
    }
}

// ============================================================================
// ENTRY POINT
// ============================================================================

// Execute only if called directly from command line
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $runner = new SalesLayerTestRunner();
    $runner->execute($argv);
}
?>
