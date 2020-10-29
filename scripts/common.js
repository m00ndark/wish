
// --- LOAD AJAX PAGE ----------------------------------------------


var avoidPageCaching = 1; // avoid page caching after initial request? (1=yes, 0=no)

function loadPage(pageRequest, containerId)
{
	if (pageRequest.readyState == 4 /* 4=DONE */ && (pageRequest.status == 200 || window.location.href.indexOf("http") == -1))
	{
		document.getElementById(containerId).innerHTML = pageRequest.responseText;
		doCustomDialogAction();
		document.getElementById(containerId).style.display = "block";
	}
}

function openAjaxPage(url, containerId)
{
	var pageRequest = false;
	if (window.XMLHttpRequest) // if Mozilla, Safari, IE7 etc
	{
		pageRequest = new XMLHttpRequest();
	}
	else if (window.ActiveXObject) // if IE < 7
	{
		try
		{
			pageRequest = new ActiveXObject("Msxml2.XMLHTTP");
		}
		catch (e)
		{
			try
			{
				pageRequest = new ActiveXObject("Microsoft.XMLHTTP");
			}
			catch (e)
			{
				return false;
			}
		}
	}
	else
	{
		return false;
	}
	pageRequest.onreadystatechange = function()
	{
		loadPage(pageRequest, containerId);
	}
	avoidCachingParameter = (avoidPageCaching ? (url.indexOf("?") != -1 ? "&" : "?") + new Date().getTime() : "");
	pageRequest.open("GET", url + avoidCachingParameter, true);
	pageRequest.send(null);
	return true;
}



// --- DIALOG SIZE AND CUSTOM ACTION -------------------------------


function setDialogSize(overlayId, loaderId, dialogId)
{
	var arrayPageSize = getPageSize();
	var arrayPageScroll = getPageScroll();

	var overlay = document.getElementById(overlayId);
	var loader = document.getElementById(loaderId);
	var dialog = document.getElementById(dialogId);

	overlay.style.height = arrayPageSize[1] + "px";
	// -- ( top coords relative to page ) --
	// pageScroll: compensation for scrolled page - i.e. dialog should be placed relative to window, not to page
	// pageSize: height of window
	// 90: height of dialog content
	// 44: height of dialog margin and border
	// pageSize / 7: offset upwards - an appropriate amount to adjust the dialog from the middle and up
	loader.style.top = (arrayPageScroll[1] + (arrayPageSize[3] - loader.height - (arrayPageSize[3] / 7)) / 2) + "px";
	dialog.style.top = (arrayPageScroll[1] + (arrayPageSize[3] - 90 - 44 - (arrayPageSize[3] / 7)) / 2) + "px";
	// -- ( left coords relative to page ) --
	// pageSize: width of page
	// 520: width of dialog content
	// 44: width of dialog margin and border
	// 17: width of scrollbars
	loader.style.left = ((arrayPageSize[0] - loader.width - 17) / 2) + "px";
	dialog.style.left = ((arrayPageSize[0] - 520 - 44 - 17) / 2) + "px";
}

function doCustomDialogAction()
{
	// dummy function, defined here in case it is not defined in the page
}



// --- CALCULATE PAGE SIZES ----------------------------------------


// getPageScroll()
// Returns array with x,y page scroll values.
// Core code from - quirksmode.org
function getPageScroll()
{
	var yScroll;
	if (self.pageYOffset)
	{
		yScroll = self.pageYOffset;
	}
	else if (document.documentElement && document.documentElement.scrollTop)
	{	 // Explorer 6 Strict
		yScroll = document.documentElement.scrollTop;
	}
	else if (document.body)
	{ // all other Explorers
		yScroll = document.body.scrollTop;
	}

	return new Array('', yScroll);
}

// getPageSize()
// Returns array with page width, height and window width, height
// Core code from - quirksmode.org
// Edit for Firefox by pHaez
function getPageSize()
{
	var xScroll, yScroll;
	if (window.innerHeight && window.scrollMaxY)
	{
		xScroll = document.body.scrollWidth;
		yScroll = window.innerHeight + window.scrollMaxY;
	}
	else if (document.body.scrollHeight > document.body.offsetHeight)
	{ // all but Explorer Mac
		xScroll = document.body.scrollWidth;
		yScroll = document.body.scrollHeight;
	}
	else
	{ // Explorer Mac...would also work in Explorer 6 Strict, Mozilla and Safari
		xScroll = document.body.offsetWidth;
		yScroll = document.body.offsetHeight;
	}

	var windowWidth, windowHeight;
	if (self.innerHeight)
	{	// all except Explorer
		windowWidth = self.innerWidth;
		windowHeight = self.innerHeight;
	}
	else if (document.documentElement && document.documentElement.clientHeight)
	{ // Explorer 6 Strict Mode
		windowWidth = document.documentElement.clientWidth;
		windowHeight = document.documentElement.clientHeight;
	}
	else if (document.body)
	{ // other Explorers
		windowWidth = document.body.clientWidth;
		windowHeight = document.body.clientHeight;
	}

	// for small pages with total height less then height of the viewport
	if (yScroll < windowHeight)
	{
		pageHeight = windowHeight;
	}
	else
	{
		pageHeight = yScroll;
	}

	// for small pages with total width less then width of the viewport
	if (xScroll < windowWidth)
	{
		pageWidth = windowWidth;
	}
	else
	{
		pageWidth = xScroll;
	}

	return new Array(pageWidth, pageHeight, windowWidth, windowHeight);
}



// --- DIALOG SIZE AND CUSTOM ACTION -------------------------------


function getPosition(obj)
{
	var curleft = curtop = 0;
	if (obj.offsetParent)
	{
		do
		{
			curleft += obj.offsetLeft;
			curtop += obj.offsetTop;
		}
		while (obj = obj.offsetParent);
	}
	return [curleft,curtop];
}



// -----------------------------------------------------------------
