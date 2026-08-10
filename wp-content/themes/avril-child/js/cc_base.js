function cc_base () {
    function iconMap(isDark, e, path, address, array) {
        Object.assign(e.style, { textDecoration: `none`, fontSize: `16px` });
        const icon = createTag("span");
        icon.style.backgroundPosition = array? `-${(59 * array[0]) + 22}px -${(43 * array[1]) + (isDark? 175 : 22)}px` : `0px 0px`;
        Object.assign(icon.style, { margin: '0px 0px 0px 0px', display: `inline-block`, width: `45px`, height: `45px`, background: `url('`+ path + address +`') no-repeat left center` });
        if (array) Object.assign(icon.style, { backgroundPosition: `-${(59 * array[0]) + 22}px -${(43 * array[1]) + (isDark? 175 : 22)}px`, backgroundSize: '320px 320px', verticalAlign: `-18px` });
        else Object.assign(icon.style, { backgroundSize: '40px 40px', verticalAlign: `-10px` });
        e.appendChild(icon);
    }
    function iconIndex(e, path, address) {
        Object.assign(e.style, { textDecoration: `none`, fontSize: `16px` });
        const icon = createTag("span");
        Object.assign(icon.style, {
            margin: '0px 0px 0px 8px',
            display: `inline-block`,
            width: `35px`,
            height: `35px`,
            background: `url('`+ path + address +`') no-repeat center center`,
            backgroundSize: '35px 35px',
            backgroundPosition: `0px 0px`,
            verticalAlign: `-8px`
        });
        e.appendChild(icon);
    }
    function image_efect(image) {
        const mask=createTag("div");
        const img=createTag("img");
        img.src=image.src;
        Object.assign(mask.style,{ position:"fixed", left:"0", top:"0", width:"100vw", height:"100vh", zIndex:"99999", background:"rgba(0,0,0,.9)", display:"flex", alignItems:"center", justifyContent:"center", opacity:"0", transition:"opacity .25s ease" });
        Object.assign(img.style,{ width:"98%", height:"98%", objectFit:"contain", transform:"scale(.92)", transition:"transform .3s ease" });
        mask.appendChild(img);
        document.body.appendChild(mask);
        requestAnimationFrame(()=> {
            mask.style.opacity="1";
            img.style.transform="scale(1)";
        });
        mask.addEventListener("click", ()=> {
            mask.style.opacity="0";
            img.style.transform="scale(.92)";
            setTimeout(()=>mask.remove(),250);
        });
    }
    const contentLists = document.querySelectorAll(".post-content ul, .entry-content ul, article ul");
    for (const e of contentLists) {
        Object.assign(e.style, { marginLeft: '2em' });
    }
    for (const e of getTagName("oc")) {
        if (!e.getAttribute("dcol") || e.getAttribute("dcol") === "0") continue;
        const d = getAttr(e, "dcol");
        if (d <= 1) continue;
        const dcol = (d * 2) - 2;
        Object.assign(e.style, { marginLeft: '2em', textIndent: `${dcol}em` });
    }
    for (const e of getTagName("a")) {
        const type = e.getAttribute("type");
        if (!type) continue;
        const text = e.textContent;
        const href = e.getAttribute("href");
        const isDark = e.hasAttribute("dark");
        const short = href.startsWith("/");
        Object.assign(e, {
            href: short? cc.uploads + href : href,
            textContent: "",
            target: "_self"
        });
        if (cc.social.icon[type]) {
            iconMap(isDark, e, cc.uploads, cc.social.media_url, cc.social.icon[type]);
        } else if (type === "cc_logo") {
            e.href = short? cc.host + href : href;
            iconIndex(e, cc.sgvdir, "cc.svg");
        } else if (type === "pdf") {
            e.href = short? cc.host + href : href;
            iconMap(isDark, e, cc.sgvdir, "pdf.svg");
        }
        const node = createTag("span");
        node.textContent = (e.getAttribute("caption")? e.getAttribute("caption") + " " : "") + text;
        Object.assign(node.style, { fontSize: '18px' });
        e.appendChild(node);
        e.appendChild(createTag("br"));
    }
    for (const e of getTagName("video")) {
        let link = e.getAttribute("src");
        if (!(/^https?:\/\//.test(link))) {
            if (link.startsWith("/")) {
                e.src = cc.host + link;
                cc.player.push(e);
                lnJS(`${cc.jsdir}/${cc.js_modules.cc_video}`, null);
                break;
            }
        }
    }
    for (const e of getTagName("img")) {
        let link = e.getAttribute("src");
        if (!(/^https?:\/\//.test(link))) {
            if (link.startsWith("/")) { link = cc.host + link; }
        }
        let type = e.getAttribute("type");
        if (!type) continue;
        if (type === "large") {
            e.className = 'cc_image';
            e.src = link;
            e.style.width = "100%";
            e.addEventListener("dblclick", function () { image_efect(this); });
        } else {
            const img_frame = createTag("div");
            const image = createTag("img");
            image.src = link;
            image.className = 'cc_image';
            if (type === "medium") image.style.width = cc.isMobile? "100%" : "80%";
            else if (type === "small") image.style.width = cc.isMobile? "80%" : "60%";
            window.addEventListener("orientationchange", function () {
                orientation(image.style, window.matchMedia("(orientation: portrait)").matches, { width: "80%" }, { width: "60%" });
            });
            Object.assign(img_frame.style, { width: "100%", display: "flex", justifyContent: "center" });
            image.addEventListener("dblclick", function () { image_efect(this); });
            img_frame.appendChild(image);
            e.parentElement.insertBefore(img_frame, e);
            e.remove();
        }
    }
}
