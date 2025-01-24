<?php

/**add request button, or launch a modal with the location table if variable locations */
		if($requestLink) {
			if($_ARCHON->config->RequestHasConsistentLocation){
				echo("<a href='" . $requestLink . "' target='_blank' tabindex='0' role='button'>");
			} else {
				echo("<span id='requestModalLink' tabindex='0' role='button'>");
			}
			echo("<img src='" . $_ARCHON->PublicInterface->ImagePath . "/box.png' alt='Request' style='padding-right:2px'/>");
			echo($_ARCHON->config->RequestLinkText ? $_ARCHON->config->RequestLinkText : "Submit request");
			echo("</span> | ");
		}
		
?>

<!-- The Modal to show request locations -->
<div id="requestModal" class="request-modal" style="display:none">

  <!-- Modal content -->
  <div class="request-modal-content" aria-label="Submit request options" role="dialog" aria-modal="true">
    <span class="request-modal-close" aria-label="close" tabindex="0" role='button'>&times;</span>
      <?php
		if(file_exists("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/openlocationtable.inc.php")){
			include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/openlocationtable.inc.php");
		} else {
			echo("Please see location table below at left.");
		}
		?>
  </div>

</div>

<script>
var modal = document.getElementById("requestModal");

var btn = document.getElementById("requestModalLink");

var span = document.getElementsByClassName("request-modal-close")[0];

function openRequestModal() {
	modal.style.display = "block";
	span.focus();
	document.addEventListener('keydown', addESC);
}

function closeRequestModal(){
	modal.style.display = "none";
	btn.focus();
	document.removeEventListener('keydown', addESC);
}

var addESC = function(e) {
  if (e.keyCode == 27) {
    closeRequestModal();
  } 
};

const  focusableElements =
    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

const firstFocusableElement = modal.querySelectorAll(focusableElements)[0]; // get first element to be focused inside modal
const focusableContent = modal.querySelectorAll(focusableElements);
const lastFocusableElement = focusableContent[focusableContent.length - 1]; // get last element to be focused inside modal


document.addEventListener('keydown', function(e) {
  let isTabPressed = e.key === 'Tab' || e.keyCode === 9;

  if (!isTabPressed) {
    return;
  }

  if (e.shiftKey) { // if shift key pressed for shift + tab combination
    if (document.activeElement === firstFocusableElement) {
      lastFocusableElement.focus(); // add focus for the last focusable element
      e.preventDefault();
    }
  } else { // if tab key is pressed
    if (document.activeElement === lastFocusableElement) { // if focused has reached to last focusable element then focus first focusable element after pressing tab
      firstFocusableElement.focus(); // add focus for the first focusable element
      e.preventDefault();
    }
  }
});

btn.addEventListener('click', function() {
  openRequestModal();
});

btn.addEventListener("keypress", function(event) {
  if (event.key === "Enter") {
	openRequestModal();
  }
});

btn.addEventListener("keydown", function(event) {
  if (event.keyCode == 32) {
  event.preventDefault();
	openRequestModal();
  }
});

span.addEventListener('click', function() {
  closeRequestModal();
});

span.addEventListener("keypress", function(event) {
  if (event.key === "Enter") {
	closeRequestModal();
  }
});

span.addEventListener("keydown", function(event) {
  if (event.keyCode == 32) {
    event.preventDefault();
    closeRequestModal();
  }
});

window.onclick = function(event) {
  if (event.target == modal) {
    closeRequestModal();
  }
}



const filterBox = document.querySelector('#requestModal .locationFilter');
var $k =jQuery.noConflict();
filterBox.addEventListener('input', filterBoxes);

function filterBoxes(e) {
	var filterValue = (e.target.value).toLowerCase();
    $k("#requestModal .locationTableBody tr").filter(function() {
        var $t = $k(this).children().last();
		$k(this).toggle($k($t).text().toLowerCase().indexOf(filterValue) > -1)
    });
    }

const filterSelect = document.querySelector('#requestModal #locations');
if (filterSelect){
	filterSelect.addEventListener('input',filterSelection);
}

function filterSelection(f) {
	var selectValue = $k('#requestModal #locations option:selected').text().toLowerCase();
  if(selectValue =="all locations"){
    selectValue =" ";
  }
	$k("#requestModal .locationTableBody tr").filter(function() {
		$k(this).toggle($k(this).text().toLowerCase().indexOf(selectValue) > -1)
    });
}

</script>

