document.addEventListener("DOMContentLoaded",(function(){function e(e){var t=function(e){if("#"===e)return null;try{var t=e;0===e.indexOf("/")&&(t="".concat(window.location.origin).concat(e));var n=new URL(t);return n.searchParams.set("InlinePreview","1"),n.href}catch(e){}return null}(e.getAttribute("href"));t&&(e.setAttribute("href",t),e.href=t)}document.querySelectorAll("a").forEach((function(t){e(t)}));var t=new URL(window.location.href);t.searchParams.delete("InlinePreview"),window.parent.document.dispatchEvent(new CustomEvent("betamaskTopBarLoaded",{detail:{urls:{'a[data-item="topbar-1"]':document.querySelector("meta[name='betamask-page-url']").getAttribute("content")},history:{title:document.title,url:t.href}}})),document.addEventListener("click",(function(t){var n;t.target&&null!==(n=t.target)&&void 0!==n&&n.href&&e(t.target),window.parent.document.dispatchEvent(new CustomEvent("betamaskTopBarLoaded",{detail:{close:"all"}}))}))}));