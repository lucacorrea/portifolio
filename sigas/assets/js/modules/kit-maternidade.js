"use strict";
document.addEventListener("DOMContentLoaded",()=>{document.querySelectorAll("[data-program-focus]").forEach(el=>el.addEventListener("click",()=>document.querySelector(el.dataset.programFocus)?.scrollIntoView({behavior:"smooth",block:"start"})));});
