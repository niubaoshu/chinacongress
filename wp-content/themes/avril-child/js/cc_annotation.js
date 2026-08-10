function cc_annotation (socialLinks, credits) {
    function display(type, root, num, item, name) {
        if (num > 0) {
            const node = createTag("span");
            node.textContent = "、";
            wrapper.appendChild(node);
        } else {
            wrapper = createTag("div");
            const lb = createTag("span");
            Object.assign(lb.style, { display: "inline-block", width: "40%", textAlign: "right", marginRight: "8px" });
            lb.textContent = item.label + "：";
            wrapper.appendChild(lb);
        }
        const cd = createTag("a");
        Object.assign(cd.style, { display: "inline-block" });
        Object.assign(cd, { textContent: name, href: item.link, title: item.title, rel: "noopener noreferrer" });
        wrapper.appendChild(cd);
        root.appendChild(wrapper);
    }
    function socials(root, socialLinks) {
        const cc_links = createTag("div");
        Object.assign(cc_links.style, { display: "block", alignItems: "center", padding: '0px 0px 20px 0px', textAlign: 'center' });
        for (const item of socialLinks) {
            var link = createTag("a");
            var ccwatch = createTag("span");
            Object.assign(ccwatch.style, {
              marginLeft: '20px',
              display: 'inline-block',
              width: '60px',
              height: '60px',
              backgroundSize: 'cover',
              backgroundPosition: 'center',
              backgroundRepeat: 'no-repeat',
              background: '#000 url("'+ cc.uploads + item.img +'") center center / cover no-repeat',
              borderRadius: '50%',
              border: '1px solid rgb(245, 245, 245)'
            });
            Object.assign(link, { title : item.title, href: item.url, rel: "noopener noreferrer" });
            link.appendChild(ccwatch);
            cc_links.appendChild(link);
        }
        root.appendChild(cc_links);
    }
    function load(root, num, item) {
        if (item?.author != null) display(1, root, num, item, item.author);
        else if (item?.editor != null) display(2, root, num, item, item.editor);
        else if (item?.uploader != null) display(3, root, num, item, item.uploader);
    }
    const annotation = getID("annotation");
    Object.assign(annotation.style, { paddingTop: '20px', marginTop: '80px', marginBottom: '50px', borderTop: '1px solid #999', fontFamily: '"Microsoft YaHei", "Noto Sans CJK SC", sans-serif', fontStyle: 'italic', fontSize: '15px' });
    let wrapper;
    socials(annotation, socialLinks);
    for (const item of credits ?? []) {
        if (Array.isArray(item)) {
            let n = 0;
            for (const obj of item) { load(annotation, n, obj); n++; }
        } else load(annotation, 0, item);

    }
}
