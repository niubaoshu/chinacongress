function appreciation_letter () {
    function certificate_head (num, salutation, element) {
        const right_content = createTag("span");
        Object.assign(right_content.style, { position: 'absolute', top: '15px', right: '10px', background: 'url("'+cc.uploads+'/2026/07/congress-250.png") center center / cover no-repeat', backgroundSize: '100px auto', transform: 'translate(0%, -23%)', width: '100px', height: '35px', zIndex: '10', opacity: '0.15' });
        element.appendChild(right_content);
        const left_content = createTag("span");
        Object.assign(left_content.style, { position: 'absolute', top: '15px', left: '10px', background: 'url("'+cc.uploads+'/2026/07/logo_128.png") center center / cover no-repeat', transform: 'translate(0%, -25%)', width: '35px', height: '35px', zIndex: '10', opacity: '0.6' });
        element.appendChild(left_content);
        const line = createTag("div");
        Object.assign(line.style, { position: 'absolute', top: '12px', left: '40px', transform: 'translate(0%, -3%)', fontSize: '18px', textAlign: 'left', width: '80%', height: '35px', zIndex: '10', color: 'rgba(0, 0, 40, 0.8)' });
        line.innerHTML = "感谢状 " + num + "： " + salutation;
        element.id = "salutation_" + num;
        element.appendChild(line);
    }
    function tips_load (isBody, element) {
      const index = createTag("div");
      const line = element.innerHTML;
      element.innerHTML = "";
      Object.assign(index.style, { padding: '0px 30px 0px 30px', color: 'rgb(0, 0, 60)'});
      index.innerHTML = line;
      element.appendChild(index);
    }
    function certificate (num, salutation, elm, images, tips) {
        const bdColor = "rgb(225, 225, 225)";
        Object.assign(images, { src: cc.uploads + images.getAttribute("src") });
        Object.assign(images.style, { width: '100%', borderRadius: '0px 0px 8px 8px', borderStyle: 'solid solid none', borderWidth: '1px 1px 0px', borderColor: bdColor, display: 'inline-block' });
        tips_load(true, tips);
        Object.assign(tips.style, { width: '100%', marginTop: '-2.5%', borderRadius: '0px 0px 8px 8px', borderWidth: '0px 1px 1px', borderStyle: 'solid', borderColor: bdColor, padding: '15px 0px 15px 0px', display: 'block', textIndent: '2em', background: 'url("'+cc.uploads+'/2026/08/bg_certificate.jpg") center center / cover no-repeat', transform: 'translate(0%, -8%)' });
        const cc_head = createTag("div");
        Object.assign(cc_head.style, { width: '100%', borderRadius: '8px 8px 0px 0px', borderWidth: '1px 1px 0px', borderStyle: 'solid', borderColor: bdColor, position: 'relative', top: "30px", left: "0px", zIndex: '999999',  height: '40px', background: 'url("'+cc.uploads+'/2026/08/bg_certificate.jpg") center center / cover no-repeat', transform: 'translate(0%, -8%)' });
        certificate_head(num, salutation, cc_head);
        elm.insertBefore(cc_head, elm.firstChild);
        Object.assign(elm.style, { marginTop: '120px' });
    }
    let num = 1;
    const root = getID("appreciationCertificate");
    for (const elm of root.children) {
        if (elm.children.length == 3) { certificate(num, elm.children[2].innerHTML, elm, elm.children[0], elm.children[1]); num++; }
    }
    Object.assign(root.style, { display: 'block', visibility: 'visible' });
}
