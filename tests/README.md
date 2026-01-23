# 🚀 SalesLayer WooCommerce - Automated Testing

Complete automated testing system for the SalesLayer WooCommerce plugin that validates data synchronization from JSON files to WordPress/WooCommerce.

## 📋 Table of Contents

- [Description](#-description)
- [Main Features](#-main-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Available Commands](#-available-commands)
- [File Structure](#-file-structure)
- [Test Process](#-test-process)
- [Usage Examples](#-usage-examples)
- [Logs and Debug](#-logs-and-debug)
- [Troubleshooting](#-troubleshooting)

## 📖 Description

This system completely automates the SalesLayer plugin testing process:

1. **Reads JSON files** with mockup data (categories, products, variants)
2. **Automatically configures** WooCommerce attributes (color, size)
3. **Inserts data** into `slyr_wc_api_syncdata` table using WordPress `$wpdb`
4. **Waits for processing** by SalesLayer cron (configurable)
5. **Validates processed data** in WordPress/WooCommerce
6. **Verifies system logs** (optional)
7. **Optionally cleans** data after validation (use `no-cleanup` to skip)
8. **Generates detailed reports** of the process

## ✨ Main Features

- 🔧 **Automatic configuration** of WooCommerce attributes
- 🧪 **Complete end-to-end testing** of synchronization
- 🔍 **Granular validation** of categories, products and variants
- 🗑️ **Complete data cleanup** capabilities
- 📊 **Detailed reports** with performance metrics
- 📄 **Log validation** of SalesLayer and WordPress
- 🎯 **Specific commands** for each testing phase
- 🔄 **Native integration** with WordPress using `$wpdb`
- 🔇 **Silent mode by default** - only final result in terminal
- 📋 **Complete details** always in logs with timestamp

## ⚙️ Requirements

- ✅ **WordPress** running (6.0+)
- ✅ **WooCommerce** installed and activated (8.0+)
- ✅ **SalesLayer WooCommerce plugin** installed and activated (2.5.0+)
- ✅ **PHP CLI** available (8.1+)
- ✅ **WordPress database access**
- ✅ **Table `slyr_wc_api_syncdata`** exists

## 🔧 Installation

The system is ready to use. You just need:

1. Ensure WordPress, WooCommerce and SalesLayer plugin are working
2. Have test JSON files in `mockup_data/`
3. Run from command line

## 🎯 Available Commands

### **Main Commands**

```bash
# Run complete automated test (minimal output)
php run_autotest.php run [minutes] [no-cleanup]

# Clean ALL data from slyr_wc_api_syncdata table (⚠️ CAUTION: Deletes everything)
php run_autotest.php cleanup

# Show complete system status
php run_autotest.php status

# Validate environment before running tests
php run_autotest.php validate
```

### **Validation Commands**

```bash
# Validate elements already processed in WordPress/WooCommerce
php run_autotest.php validate-data

# Validate SalesLayer and WordPress logs
php run_autotest.php validate-logs

# Configure WooCommerce attributes (color, size)
php run_autotest.php setup-attributes
```

### **Cleanup Commands**

```bash
# Remove processed data from WordPress/WooCommerce
php run_autotest.php cleanup-processed

# Clean ALL data from slyr_wc_api_syncdata table (⚠️ CAUTION: Deletes everything)
php run_autotest.php cleanup
```

### **Information Commands**

```bash
# Comprehensive records count (total, test, pending, processed)
php run_autotest.php count

# Show complete help
php run_autotest.php help
```

## 🔇 System Behavior

### **Silent Mode by Default**
- **File**: `run_autotest.php`
- **Output**: Only final result in terminal
- **Details**: Complete in log with timestamp
- **Usage**: Ideal for CI/CD and automated scripts

### **System Output**

**Terminal (Clean):**
```
🚀 SalesLayer Test Automatizado
📄 Log detallado: tests/logs/autotest_2025-07-29.log
⏳ Ejecutando test (20 min)...
✅ TEST EXITOSO
📊 Procesados: 12 elementos
⏱️ Tiempo: 1205s
```

**Log (Detailed):**
```
[2025-07-29 10:30:15] 🚀 SalesLayer AutoTest started using WordPress $wpdb - 2025-07-29 10:30:15
[2025-07-29 10:30:15] 📊 Syncdata table: slyr_wc_api_syncdata
[2025-07-29 10:30:16] ✅ WordPress $wpdb available (type: wpdb)
[2025-07-29 10:30:16] 📁 Mockup_data directory: /var/www/html/wp-content/plugins/saleslayer_woocommerce/tests/mockup_data
[2025-07-29 10:30:16] 📁 Logs directory: /var/www/html/wp-content/plugins/saleslayer_woocommerce/tests/logs/
[2025-07-29 10:30:16] 📖 Reading mockup data...
[... all detail lines ...]
```

## 📁 File Structure

```
tests/
├── 📄 SalesLayerAutoTest.php     # Main system class
├── 📄 run_autotest.php          # Executor script with minimal output ⭐
├── 📄 README.md                 # This documentation
├── 📁 mockup_data/              # Test data
│   ├── 📄 README.md             # Mockup data documentation
│   ├── 📁 categories/           # Category JSON files
│   ├── 📁 products/             # Product JSON files
│   └── 📁 variants/             # Variant JSON files
└── 📁 logs/                     # Automatic logs
    └── 📄 autotest_YYYY-MM-DD.log
```

## 🔄 Test Process

### **Step 1: Data Reading**
- Reads JSON files from `mockup_data/categories/`, `mockup_data/products/`, `mockup_data/variants/`
- Validates format and data structure
- Organizes by element type

### **Step 2: Attribute Configuration**
- Verifies and creates necessary WooCommerce attributes (color, size)
- Registers attribute taxonomies in WordPress
- Clears WooCommerce attribute cache

### **Step 3: Database Insertion**
- Inserts data into `slyr_wc_api_syncdata` table using WordPress `$wpdb`
- Records inserted IDs for tracking
- Handles insertion errors

### **Step 4: Cron Wait**
- Waits configurable time (default 10 minutes)
- Checks every 30 seconds if records were processed
- Automatically terminates when table is empty

### **Step 5: Data Validation**
- Verifies that data was created correctly in WordPress/WooCommerce
- Compares original data with processed data
- Validates categories, products and variants with their attributes

### **Step 6: Log Verification**
- Reviews WordPress and SalesLayer logs
- Searches for synchronization-related errors
- Validates Apache/PHP logs for critical errors

### **Step 7: Automatic Cleanup (Optional)**
- Automatically removes processed test data
- Cleans created categories, products and variants
- Maintains database integrity

## 💡 Usage Examples

### **Basic Complete Test**
```bash
# Test with 10 minutes wait and automatic cleanup (default)
php run_autotest.php run

# Test with 15 minutes wait and automatic cleanup
php run_autotest.php run 15

# Test with 10 minutes without automatic cleanup
php run_autotest.php run 10 no-cleanup
```

### **Initial Configuration**
```bash
# Configure WooCommerce attributes before first test
php run_autotest.php setup-attributes

# Validate that everything is ready
php run_autotest.php validate

# View current system status
php run_autotest.php status
```

### **Data Validation**
```bash
# Validate elements already processed in WordPress/WooCommerce
php run_autotest.php validate-data

# Validate SalesLayer and WordPress logs
php run_autotest.php validate-logs

# View comprehensive counts
php run_autotest.php count          # Total, test, pending, processed
```

### **Data Cleanup**
```bash
# Clean ALL data from slyr_wc_api_syncdata table (⚠️ CAUTION: Deletes everything)
php run_autotest.php cleanup

# Remove processed data from WordPress/WooCommerce
php run_autotest.php cleanup-processed
```

### **Recommended Complete Flow**
```bash
# 1. Configure attributes (first time only)
php run_autotest.php setup-attributes

# 2. Validate environment
php run_autotest.php validate

# 3. View initial status
php run_autotest.php status

# 4. Run complete test
php run_autotest.php run

# 5. Validate results (optional if automatic cleanup wasn't done)
php run_autotest.php validate-data

# 6. Clean processed data (optional if automatic cleanup wasn't done)
php run_autotest.php cleanup-processed
```

## 📄 Logs and Debug

### **Log Files**
- **Location:** `tests/logs/autotest_YYYY-MM-DD.log`
- **Content:** Detailed log of the entire process
- **Format:** `[YYYY-MM-DD HH:MM:SS] [LEVEL] Message`

### **Log Levels**
- **INFO:** General process information
- **DEBUG:** Technical details for debugging
- **ERROR:** Errors requiring attention
- **WARNING:** Non-critical warnings

### **Log Example**
```
[2025-07-24 10:30:15] [INFO] 🚀 SalesLayer AutoTest started using WordPress $wpdb
[2025-07-24 10:30:15] [INFO] 📊 Syncdata table: wp_slyr_wc_api_syncdata
[2025-07-24 10:30:16] [INFO] ✅ WordPress $wpdb available (type: wpdb)
[2025-07-24 10:30:16] [INFO] 📁 Category read: category_1001.json
[2025-07-24 10:30:17] [DEBUG] ✅ Record inserted with ID: 1234
```

## 🔧 Troubleshooting

### **Error: WordPress not loaded**
```bash
# Verify you're in the correct directory
cd /path/to/wordpress/wp-content/plugins/saleslayer_woocommerce/tests

# Verify WordPress works
php run_autotest.php validate
```

### **Error: SalesLayer plugin not active**
- Verify the plugin is installed
- Activate the plugin in WordPress admin
- Verify that table `slyr_wc_api_syncdata` exists

### **Error: WooCommerce attributes not configured**
```bash
# Configure attributes automatically
php run_autotest.php setup-attributes

# Verify status after configuration
php run_autotest.php validate
```

### **Error: Mockup files not found**
```bash
# Verify files
ls -la mockup_data/categories/
ls -la mockup_data/products/
ls -la mockup_data/variants/

# View complete status
php run_autotest.php status
```

### **Test hangs waiting**
- SalesLayer cron may be disabled
- Check WordPress logs for errors
- Reduce wait time: `php run_autotest.php run 5`
- Check records count: `php run_autotest.php count`

### **Data doesn't validate correctly**
- Verify WooCommerce is working
- Review logs in `tests/logs/autotest_YYYY-MM-DD.log`
- Verify JSON data structure
- Run specific validation: `php run_autotest.php validate-data`

### **Errors in SalesLayer logs**
```bash
# Validate logs specifically
php run_autotest.php validate-logs

# View detailed logs
tail -f ../logs/_debug_log_saleslayer_$(date +%Y-%m-%d).dat
```

### **Problematic data cleanup**
```bash
# Clean ALL data (⚠️ CAUTION: Deletes everything)
php run_autotest.php cleanup

# Clean processed data in WordPress
php run_autotest.php cleanup-processed

# View status after cleanup
php run_autotest.php status
```

## 📊 Quick Diagnostic Commands

```bash
# Complete system diagnosis
php run_autotest.php validate && php run_autotest.php status

# Check comprehensive counts
php run_autotest.php count

# Validate data and logs
php run_autotest.php validate-data && php run_autotest.php validate-logs

# Configure and validate attributes
php run_autotest.php setup-attributes && php run_autotest.php validate
```

## 🚀 Advanced Use Cases

### **Compatibility Testing**
```bash
# Quick test to verify compatibility
php run_autotest.php run 5 no-cleanup
php run_autotest.php validate-data
php run_autotest.php cleanup-processed
```

### **Synchronization Debugging**
```bash
# Test with persistent data for debugging
php run_autotest.php run 15 no-cleanup
php run_autotest.php validate-logs
# Review data manually in WordPress admin
php run_autotest.php cleanup-processed
```

### **Performance Testing**
```bash
# Test with time measurement
time php run_autotest.php run 10
```

### **CI/CD Automation**
```bash
# Script for CI/CD pipeline
#!/bin/bash
set -e

echo "🔧 Setting up environment..."
php run_autotest.php validate || exit 1

echo "🧪 Running tests..."
php run_autotest.php run 10 || exit 1

echo "🧹 Cleaning up..."
php run_autotest.php cleanup || exit 1

echo "✅ Tests completed successfully"
```

## 📞 Support

For problems or questions:

1. **Review logs:** `tests/logs/autotest_YYYY-MM-DD.log`
2. **Run validation:** `php run_autotest.php validate`
3. **View system status:** `php run_autotest.php status`
4. **Validate specific data:** `php run_autotest.php validate-data`
5. **Check SalesLayer logs:** `php run_autotest.php validate-logs`
6. **Check documentation** for mockup data in `mockup_data/README.md`

## 📈 Metrics and Reports

The system automatically generates:

- **Detailed counts** of processed elements
- **Execution times** for each phase
- **Granular validations** by element type
- **Error reports** with specific context
- **Structured logs** with timestamps and levels

---

**Developed by Peter** | **Version 3.0** | **Silent Mode by Default** | **Compatible with SalesLayer WooCommerce 2.5.2+**
