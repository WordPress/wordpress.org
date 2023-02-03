function decodeHtml(html) {
	const txt = document.createElement("textarea");
	txt.innerHTML = html;
	return txt.value;
}

function copyAttribution(event){
	event.preventDefault();
	const attribution = document.querySelector('.attribution-text .tab.active');
	const copyButton  = document.querySelector('.attribution-copy');

	let txt = attribution.innerHTML;

	if ( attribution.classList.contains('tab-html') ) {
		txt = decodeHtml(txt);
	}
	
	navigator.clipboard.writeText(txt.trim()).then(res=>{
		copyButton.textContent = PhotoDir.copied_text;
		setTimeout(function() { restoreCopyButtonText(); }, 4000);
		  function restoreCopyButtonText(){ copyButton.textContent = PhotoDir.copy_to_clipboard_text; }
	})
}

function initCopyToCliboard(){
	const copyButton = document.querySelector('.attribution-copy');
	copyButton.addEventListener('click', e => {
		copyAttribution(e);
	 });
}

function changeAttributionTab(event, index) {
	event.preventDefault();
	const tabsContainer = document.querySelector('.attribution .tabs');
	const tab = event.target;
	// Bail if tab is already active.
	if ( tab.classList.contains('active') ) {
		return;
	}
	// Remove active class from any other tab.
	tabsContainer.querySelector('button.active').classList.remove('active');
	document.querySelector('.attribution-text .tab.active').classList.remove('active');
	// Assign active class.
	tab.classList.add('active');
	document.querySelectorAll('.attribution-text .tab')[index]?.classList.add('active');
}

function initAttributionTabs() {
	const tabsContainer = document.querySelector('.attribution .tabs');
	tabsContainer.querySelectorAll('button').forEach((tab,i) => {
		tab.addEventListener('click', e => {
			changeAttributionTab(e, i);
		});
	});
}

document.addEventListener( 'DOMContentLoaded', () => {
	initAttributionTabs();
	initCopyToCliboard();
} );
