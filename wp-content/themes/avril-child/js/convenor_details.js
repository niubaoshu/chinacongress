function convenor_details() {
    function createEllipsisSpan(content, width, paddingLeft) {
        const span = createTag("span");
        if (typeof content === "string") {
            span.innerHTML = content;
        } else if (content) {
            span.appendChild(content);
        }
        Object.assign(span.style, {
            paddingLeft: paddingLeft || '0px',
            fontSize: '16px',
            width: width,
            display: 'inline-block',
            overflow: 'hidden',
            whiteSpace: 'nowrap',
            textOverflow: 'ellipsis'
        });
        return span;
    }
    function info(elm, href, opt, words) {
        elm.innerHTML = "";
        elm.appendChild(createEllipsisSpan(opt, '20%', '5px'));
        const a = createTag("a");
        Object.assign(a, { href: href, target: '_blank', title: opt + words, innerHTML: words, className: 'contact' });
        elm.appendChild(createEllipsisSpan(a, '70%', '0px'));
        const bframe = createTag("span");
        const button = createTag("button");
        button.setAttribute("link", words.trim());
        Object.assign(bframe.style, { display: "block", textAlign: "right", width: '10%' });
        Object.assign(button, { innerHTML: "复制", title: opt + words });
        button.addEventListener("click", function () { navigator.clipboard.writeText(this.getAttribute("link")); isCopy(); });
        bframe.appendChild(button);
        elm.appendChild(bframe);
    }
    function file(elm, opt, words) {
        elm.innerHTML = "";
        const a = createTag("a");
        Object.assign(a, { href: words, target: '_blank', title: opt + words, innerHTML: "文件： " + opt.substring(0, opt.length - 2), className: 'contact' });
        elm.appendChild(createEllipsisSpan(a, '70%', '15px'));
    }
    function note(elm, opt, words) {
        elm.innerHTML = "";
        elm.appendChild(createEllipsisSpan(opt, '20%', '5px'));
        const item = createEllipsisSpan(words, '70%', '0px');
        item.title = opt + words;
        elm.appendChild(item);
    }
    function has(words, domain) {
        const escaped = domain.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        return words.match(new RegExp(`[\\w.+-]+${escaped}$`, "i"));
    }
    function getSvg(type, words) {
        if (type === "e_mail") {
            if (has(words, "@gmail.com")) return "gmail.svg";
            if (/[\w.+-]+@(outlook|hotmail|live|msn)\.com$/i.test(words)) return "ms-outlook.svg";
            return "email.svg";
        }
        if (type === "convenor_doc") {
            if (has(words, "doc")) return "ms-word.svg";
            return "pdf.svg";
        }
        const map = {
            convenor: "location.svg",
            tg_group: "telegram.svg",
            x_user: "x.svg",
            whatsapp: "whatsapp.svg"
        };
        return map[type] || "location.svg";
    }
    const details = {
        convenor: {
            func(elm, opt, words) { note(elm, opt, words); }
        },
        tg_group: {
            func(elm, opt, words) { info(elm, "https://t.me/" + words.replace(/^@/, ""), opt, words); }
        },
        e_mail: {
            func(elm, opt, words) { info(elm, "mailto:" + words, opt, words); }
        },
        x_user: {
            func(elm, opt, words) { info(elm, "https://x.com/" + words.replace(/^@/, ""), opt, words); }
        },
        whatsapp: {
            func(elm, opt, words) { info(elm, "https://wa.me/" + words.replace(/^@/, ""), opt, words); }
        },
        convenor_doc: {
            func(elm, opt, words) { file(elm, opt, words); }
        }
    };
    const convener_title = getID("convener_title");
    if (convener_title) {
        Object.assign(convener_title.style, { color: 'rgb(0, 0, 80)', background: `url("${cc.sgvdir}/person.svg")  left 0px / 35px 35px no-repeat`, display: "inline-flex", alignItems: "center", paddingLeft: "40px", height: "45px" });
    }
    const contact_title = getID("contact_title");
    if (contact_title) {
        Object.assign(contact_title.style, { color: 'rgb(0, 0, 80)', marginLeft: '35px', background: `url("${cc.sgvdir}/contact-details.svg")  left 0px / 40px 40px no-repeat`, display: "inline-flex", alignItems: "center", paddingLeft: "48px", height: "45px" });
    }
    const container = getID("contact_details");
    if (container) {
        window.addEventListener("orientationchange", function () {
            orientation(container.style, window.matchMedia("(orientation: portrait)").matches, { margin: "0px 0px 0px 0px" }, { margin: "0px 20px 0px 20px" });
        });
        for (let elm of container.children) {
            let type = elm.getAttribute("type");
            if (!details[type]) {
                if (type == "convenor_map" && elm.children[0]) {
                    const gmap = elm.children[0];
                    Object.assign(elm.style, { display: 'flex', alignItems: "center", margin: "20px 0px 10px 0px", paddingLeft: '20px', paddingRight: '20px' });
                    Object.assign(gmap, { height: "250", loading: "lazy" });
                    Object.assign(gmap.style, { width: '100%', border: '1px solid rgba(0, 0, 80, 0.2)', borderRadius: '6px', display: 'inline-block' });
                }
                continue;
            }
            const match = elm.innerHTML.match(/[：:]/);
            let opt = "", words = "";
            if (match) {
                opt = elm.innerHTML.substring(0, match.index) + "： ";
                words = elm.innerHTML.substring(match.index + 1);
                details[type].func?.(elm, opt, words);
            }
            const svgName = getSvg(type, words);
            Object.assign(elm.style, {
                background: `url("${cc.sgvdir}/${svgName}") left top / contain no-repeat`,
                backgroundSize: "25px 25px",
                backgroundPosition: "0px 4px",
                margin: "0px 20px 0px 20px",
                paddingLeft: '23px',
                fontSize: '16px',
                textAlign: 'left',
                display: 'flex',
                alignItems: "center",
                verticalAlign: 'bottom'
            });
            elm.appendChild(createTag("br"));
        }
    }
    const committee_members = getID("committee_members");
    if (committee_members) {
        const committee = createTag("div");
        const members = createTag("a");
        Object.assign(members.style, { background: `url("${cc.sgvdir}/person-team.svg")  left 0px / 45px 45px no-repeat`, display: "inline-flex", alignItems: "center", paddingLeft: "50px", height: "50px" });
        Object.assign(members, { href: cc.committee_members, target: '_self', innerHTML: "中国议会（临时）筹备委员会 委员召集人名单", className: 'contact' });
        Object.assign(committee_members.style, { margin: "50px 0px 30px 0px", textAlign: 'center', width: '100%', fontSize: '18px', fontWeight: 'bold' });
        committee.appendChild(members);
        committee_members.appendChild(committee);
    }
}
