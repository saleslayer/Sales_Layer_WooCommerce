jQuery(document).ready(function($)
{
    function toggleFaq($question) {
        var $answer = $question.next(".faq-answer");
        if ($answer.is(":visible")) {
            $answer.slideUp();
            $question.removeClass("active").attr("aria-expanded", "false");
        } else {
            $answer.slideDown();
            $question.addClass("active").attr("aria-expanded", "true");
        }
    }

    // Questions are plain divs in the views; give them button semantics so they work with the keyboard.
    $(".faq-question").each(function(index) {
        var $question = $(this);
        var $answer = $question.next(".faq-answer");
        var answerId = $answer.attr("id") || ("slyr-faq-answer-" + index);
        $answer.attr("id", answerId);
        $question.attr({
            "role": "button",
            "tabindex": "0",
            "aria-controls": answerId,
            "aria-expanded": $answer.is(":visible") ? "true" : "false"
        });
    });

    $(".faq-question").on("click", function() {
        toggleFaq($(this));
    });

    $(".faq-question").on("keydown", function(e) {
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            toggleFaq($(this));
        }
    });
});
