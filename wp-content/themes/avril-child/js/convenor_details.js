function convenor_details() {
    function info(elm, href, opt, words) {
        elm.innerHTML = "";
        option = createTag("span");
        option.innerHTML = opt;
        Object.assign(option.style, { paddingLeft: '5px', fontSize: '16px', width: '20%', display: 'inline-block', overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis'  });
        elm.appendChild(option);
        a = createTag("a");
        text = createTag("span");
        Object.assign(text.style, { width: '70%', display: 'inline-block', overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis' });
        Object.assign(a, { href: href, target: '_blank', title: opt + words, innerHTML: words, className: 'contact' });
        text.appendChild(a);
        elm.appendChild(text);
        bframe = createTag("span");
        button = createTag("button");
        button.setAttribute("link", words.trim());
        Object.assign(bframe.style, { display: "block", textAlign: "right", width: '10%' });
        Object.assign(button, { innerHTML: "复制", title: opt + words });
        Object.assign(button.style, { display: 'inline-block', width: 'auto', height: 'auto', minHeight: '0', padding: '3px 5px', lineHeight: 'normal', boxSizing: 'border-box', color: 'rgb(166, 210, 255)', background: "white", fontSize: '12px', border: '1px solid rgba(166, 210, 255, 0.6)', borderRadius: '3px' });
        button.addEventListener("click", function () { navigator.clipboard.writeText(this.getAttribute("link")); isCopy(); });
        button.addEventListener("mouseover", function () { Object.assign(this.style, { color: 'white', background: "rgba(166, 210, 255, 0.3)" }); });
        button.addEventListener("mouseout", function () { Object.assign(this.style, { color: 'rgb(166, 210, 255)', background: "white" }); });
        bframe.appendChild(button);
        elm.appendChild(bframe);
    }
    function file(elm, opt, words) {
        elm.innerHTML = "";
        a = createTag("a");
        text = createTag("span");
        Object.assign(text.style, { paddingLeft: '15px', width: '70%', fontSize: '16px', display: 'inline-block', overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis' });
        Object.assign(a, { href: words, target: '_blank', title: opt + words, innerHTML: "文件： " + opt.substring(0, opt.length - 2), className: 'contact' });
        text.appendChild(a);
        elm.appendChild(text);
    }
    function note(elm, href, opt, words) {
        elm.innerHTML = "";
        option = createTag("span");
        Object.assign(option.style, { paddingLeft: '5px', fontSize: '16px', width: '20%', display: 'inline-block', overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis'  });
        option.innerHTML = opt;
        elm.appendChild(option);
        item = createTag("span");
        Object.assign(item, { title: opt + words, innerHTML: words });
        Object.assign(item.style, { fontSize: '16px', width: '70%', display: 'inline-block', overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis'  });
        elm.appendChild(item);
    }
    function has(words, domain) {
        const escaped = domain.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        return words.match(new RegExp(`[\\w.+-]+${escaped}$`, "i"));
    }
    const details = {
        convenor: {
            svg: "location.svg",
            func(elm, opt, words) { note(elm, null, opt, words); }
        },
        tg_group: {
            svg: "telegram.svg",
            func(elm, opt, words) { info(elm, words.replace(/^@/, ""), opt, words); }
        },
        e_mail: {
            svg: "email.svg",
            func(elm, opt, words) {
                if (has(words, "@gmail.com")) { details.e_mail.svg = "gmail.svg"; }
                else if (words.match(/[\w.+-]+@(outlook|hotmail|live|msn)\.com$/i)) { details.e_mail.svg = "ms-outlook.svg"; }
                info(elm, "mailto:" + words, opt, words);
            }
        },
        x_user: {
            svg: "x.svg",
            func(elm, opt, words) { info(elm, "https://x.com/" + words.replace(/^@/, ""), opt, words); }
        },
        whatsapp: {
            svg: "pdf.svg",
            func(elm, opt, words) { info(elm, "https://wa.me/" + words.replace(/^@/, ""), opt, words); }
        },
        convenor_doc: {
            svg: "pdf.svg",
            func(elm, opt, words) {
                if (has(words, "pdf")) { details.convenor_doc.svg = "pdf.svg"; }
                else if (has(words, "doc")) { details.convenor_doc.svg = "ms-word.svg"; }
                file(elm, opt, words);
            }
        },
    };
    const convener_title = getID("convener_title");
    Object.assign(convener_title.style, { color: 'rgb(0, 0, 80)', background: `url("${cc.sgvdir}/person.svg")  left 0px / 35px 35px no-repeat`, display: "inline-flex", alignItems: "center", paddingLeft: "40px", height: "45px" });
    const contact_title = getID("contact_title");
    Object.assign(contact_title.style, { color: 'rgb(0, 0, 80)', marginLeft: '35px', background: `url("${cc.sgvdir}/contact-details.svg")  left 0px / 40px 40px no-repeat`, display: "inline-flex", alignItems: "center", paddingLeft: "48px", height: "45px" });
    const container = getID("contact_details");
    window.addEventListener("orientationchange", function () {
        orientation(container.style, window.matchMedia("(orientation: portrait)").matches, { margin: "0px 0px 0px 0px" }, { margin: "0px 20px 0px 20px" });
    });
    for(let elm of container.children) {
        let type = elm.getAttribute("type");
        if (!details[type]) {
        if (type == "convenor_map" && elm.children[0]) {
            const gmap = elm.children[0];
            Object.assign(elm.style, { display: 'flex', alignItems: "center", margin: "20px 0px 10px 0px", paddingLeft: '20px', paddingRight: '20px' });
            Object.assign(gmap, { height: "250", loading: "lazy" });
            Object.assign(gmap.style, { width:'100%', border: '1px solid rgba(0, 0, 80, 0.2)', borderRadius: '6px', display: 'inline-block' });
        } continue;
        }
        const match = elm.innerHTML.match(/[：:]/);
        if (match) { details[type].func?.(elm, elm.innerHTML.substring(0, match.index) + "： ", elm.innerHTML.substring(match.index + 1)); }
        Object.assign(elm.style, {
            background: `url("${cc.sgvdir}/${details[type].svg}") left top / contain no-repeat`,
            backgroundSize: "25px 25px",
            backgroundPosition: "0px 4px",
            margin: "0px 20px 0px 20px",
            paddingLeft: '23px',
            fontSize:'16px',
            textAlign:'left',
            display: 'flex',
            alignItems: "center",
            verticalAlign: 'bottom'
        });
        elm.appendChild(createTag("br"));
    }
    const committee_members = getID("committee_members");
    const committee = createTag("div");
    const members = createTag("a");
    Object.assign(members.style, { background: `url("${cc.sgvdir}/person-team.svg")  left 0px / 45px 45px no-repeat`, display: "inline-flex", alignItems: "center", paddingLeft: "50px", height: "50px" });
    Object.assign(members, { href: cc.committee_members, target: '_self', innerHTML: "中国议会（临时）筹备委员会 委员召集人名单", className: 'contact' });
    Object.assign(committee_members.style, { margin: "50px 0px 30px 0px", textAlign: 'center', width: '100%', fontSize: '18px', fontWeight: 'bold' });
    committee.appendChild(members);
    committee_members.appendChild(committee);
}
