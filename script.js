(function($, w, d, undef) {
	$(d).ready(function() {
		$( "#plugin_switcher_container fieldset > div input" ).change(function(event) {
			var checkbox = $(event.currentTarget);

			var ajax_message = $("<span></span>")
			.addClass("ajax_loading")
			.insertAfter(checkbox);

			$.ajax({
				type: "POST",
				url: ajaxurl,
				dataType: "text",
				data:	{
					action: 'plugin_switcher_change',
					plugin_directory: checkbox.attr("id"),
					is_checked: checkbox.prop("checked") ? "1" : "0"
				}
			})
			.done(function(data, textStatus, jqXHR) {
				if ( typeof data === "string" && data.indexOf("updatedsuccessful") != -1 ) {
					result_message(true);
				} else {
					result_message(false);
				}
			})
			.fail(function(jqXHR, textStatus, errorThrown) {
				result_message(false);
			});

			function result_message(is_successful) {
				ajax_message
				.removeClass("ajax_loading")
				.addClass( is_successful ? "success" : "error" )
				.text( is_successful ? "Uplated successful" : "Error happened");

				setTimeout(function() {
					ajax_message.hide(800, function() {
						ajax_message.remove();
					});
					}, 1000);
			}
		});
	});
})(jQuery, window, document);
