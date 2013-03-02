var localTime = new Date();
function autoDetectTimeOffset(currentTime)
{
	if (typeof(currentTime) !== 'string')
		var serverTime = currentTime;
	else
		var serverTime = new Date(currentTime);

	// Something wrong?
	if (!localTime.getTime() || !serverTime.getTime())
		return 0;

	// Get the difference between the two, set it up so that the sign will tell us who is ahead of who.
	var diff = Math.round((localTime.getTime() - serverTime.getTime())/3600000);

	// Make sure we are limiting this to one day's difference.
	diff %= 24;

	return diff;
}

// Prevent Chrome from auto completing fields when viewing/editing other members profiles
function disableAutoComplete()
{
	if (is_chrome && document.addEventListener)
		document.addEventListener("DOMContentLoaded", disableAutoCompleteNow, false);
}

// Once DOMContentLoaded is triggered, call the function
function disableAutoCompleteNow()
{
	for (var i = 0, n = document.forms.length; i < n; i++)
	{
		var die = document.forms[i].elements;
		for (var j = 0, m = die.length; j < m; j++)
			// Only bother with text/password fields?
			if (die[j].type === "text" || die[j].type === "password")
				die[j].setAttribute("autocomplete", "off");
	}
}

function calcCharLeft()
{
	var oldSignature = "", currentSignature = document.forms.creator.signature.value;
	var currentChars = 0;

	if (!document.getElementById("signatureLeft"))
		return;

	if (oldSignature !== currentSignature)
	{
		oldSignature = currentSignature;

		var currentChars = currentSignature.replace(/\r/, "").length;
		if (is_opera)
			currentChars = currentSignature.replace(/\r/g, "").length;

		
		if (currentChars > maxLength)
			document.getElementById("signatureLeft").className = "error";
		else
			document.getElementById("signatureLeft").className = "";
		
		if (currentChars > maxLength && !$("#profile_error").is(":visible"))
			ajax_getSignaturePreview(false);
		else if (currentChars <= maxLength && $("#profile_error").is(":visible"))
		{
			$("#profile_error").css({display:"none"});
			$("#profile_error").html('');
		}
	}

	setInnerHTML(document.getElementById("signatureLeft"), maxLength - currentChars);
}

function ajax_getSignaturePreview (showPreview)
{
	showPreview = (typeof showPreview === 'undefined') ? false : showPreview;
	$.ajax({
		type: "POST",
		url: smf_scripturl + "?action=xmlhttp;sa=previews;xml",
		data: {item: "sig_preview", signature: $("#signature").val(), user: $('input[name="u"]').attr("value")},
		context: document.body,
		success: function(request){
			if (showPreview)
			{
				var signatures = new Array("current", "preview");
				for (var i = 0; i < signatures.length; i++)
				{
					$("#" + signatures[i] + "_signature").css({display:""});
					$("#" + signatures[i] + "_signature_display").css({display:""}).html($(request).find('[type="' + signatures[i] + '"]').text() + '<hr />');
				}
			}

			if ($(request).find("error").text() !== '')
			{
				if (!$("#profile_error").is(":visible"))
					$("#profile_error").css({display: "", position: "fixed", top: 0, left: 0, width: "100%"});
				var errors = $(request).find('[type="error"]');
				var errors_html = '<span>' + $(request).find('[type="errors_occurred"]').text() + '</span><ul class="reset">';

				for (var i = 0; i < errors.length; i++)
					errors_html += '<li>' + $(errors).text() + '</li>';

				errors_html += '</ul>';
				$(document).find("#profile_error").html(errors_html);
			}
			else
			{
				$("#profile_error").css({display:"none"});
				$("#profile_error").html('');
			}
		return false;
		},
	});
	return false;
}