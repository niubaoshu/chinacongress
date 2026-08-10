function preparatory_committee () {
  const sgvlist = {
    chief: 'chief.svg',
    convenor: 'convenor-female.svg|convenor-male.svg',
    legal_consultant: 'legal_consultant.svg',
    political_consultant: 'political_consultant.svg',
    secretary: 'secretary.svg',
    registration: 'registration.svg',
    communications: 'communications.svg',
    design: 'design.svg',
    finance: 'finance.svg',
    volunteer: 'volunteer.svg',
    web_editor: 'web_editor.svg',
    video_creator: 'video_creator.svg',
    audio_creator: 'audio_creator.svg',
    x_campaign: 'x.svg',
    graphic_design: 'graphic_design.svg',
    network_technology: 'network_technology.svg',
    technical: 'technical.svg',
    promotion: 'promotion.svg',
    activity: 'activity.svg'
  }
  const cc_opt = { alive: '任职', left: '离职', unknow: '不详' };
  function listName(itemName) {
    for (let index of [{ name: "姓名" }, { name: "工作人员" }, { name: "状态" }]) {
      const m = createTag("span");
      if (index.name == "姓名") {
        Object.assign(m.style, { display: "inline-block", fontSize: '16px', fontWeight: '500', width: '100px', alignItems: "center", paddingLeft: "30px", height: "24px", overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis' });
      } else if (index.name == "工作人员") {
        Object.assign(m.style, { display: "inline-block", fontSize: '16px', fontWeight: '500', color: "#000", textDecoration: "none", overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis' });
      } else if (index.name == "状态") {
        Object.assign(m.style, { flexGrow: 1, textAlign: "right", whiteSpace: "nowrap", fontSize: "16px", fontWeight: "500", color: "#000" });
      }
      m.innerHTML = index.name;
      itemName.appendChild(m);
    }
  }
  function renderer (board, elm, tp, name, jobs) {
    if (!sgvlist[tp]) return;
    const files = sgvlist[tp].split("|");
    const data = board.getAttribute("data");
    const [gender, status, link] = data.split("|");
    const [female, male] = files;
    board.innerHTML = "";
    const nm = createTag("span");
    Object.assign(nm.style, { display: "inline-block", fontSize: '18px', fontWeight: '200', width: '100px', background: `url("${cc.sgvdir}/${(files.length === 2? (gender == "f"? female : male) : sgvlist[tp])}")  left 0px / 24px 24px no-repeat`, alignItems: "center", paddingLeft: "30px", height: "24px", overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis' });
    nm.innerHTML = name;
    elm.appendChild(nm);

    const job = createTag("a");
    Object.assign(job.style, { display: "inline-block", fontSize: '18px', fontWeight: '200', color: "#000", textDecoration: "none", overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis' });
    job.innerHTML = jobs;
    if (link) job.href = cc.host + link;
    elm.appendChild(job);

    const current = createTag("span");
    Object.assign(current.style, { flexGrow: 1, textAlign: "right", whiteSpace: "nowrap", fontSize: "16px", fontWeight: "150", color: "#000" });
    current.innerHTML = cc_opt[status]? cc_opt[status] : cc_opt.unknow;
    elm.appendChild(current);
  }
  function cc_header(header, doc_title, lastModifier) {
    Object.assign(header.style, { display: "flex", background: `url("${cc.sgvdir}/cc.svg")  10px 10px / 45px 45px no-repeat rgba(166, 210, 255, 0.1)`, margin: '0px 0px 0px 0px', flexWrap: "wrap", width: '100%' });
    const title = createTag("div");
    title.innerHTML = doc_title;
    Object.assign(title.style, { display: "inline", fontSize: "18px", color: 'rgb(35, 74, 107)', fontWeight: "800", display: "inline-flex", alignItems: "flex-end", paddingLeft: "42px", transform: "translateY(20px)", overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis'});
    header.appendChild(title);
    const update = createTag("div");
    update.innerHTML = '最近更新：' + lastModifier;
    Object.assign(update.style, { padding: '0px 10px 5px 10px', fontSize: "13px", fontWeight: '300', width: "100%", flexBasis: "100%", textAlign:'right', transform: "translateY(15px)" });
    header.appendChild(update);
    const itemName = createTag("div");
    Object.assign(itemName.style, { width:'100%', display: "flex", margin: '0px 0px 0px 0px', padding: '20px 20px 3px 20px', transform: "translateY(3px)" });
    listName(itemName);
    header.appendChild(itemName);
  }
  function cc_bottom(bottom) {
    Object.assign(bottom.style, { position: "relative", display: "flex", justifyContent: "space-between", alignItems: "flex-end", padding: "80px 0px 80px 0px", overflow: "hidden" });
    const bg = createTag("div");
    Object.assign(bg.style, { position: "absolute", inset: "0", opacity: "0.2", background: `url("${cc.uploads}/2026/01/logo-e1768719012316.jpg") center center / 400px auto no-repeat`, zIndex: "0" });
    bottom.appendChild(bg);
    const button = createTag("button");
    button.innerHTML = "生成文本";
    Object.assign(button.style, { color: 'rgb(166, 210, 255)', background: "white", position: "absolute", right: "20px", bottom: "10px", zIndex: "1", border: '1px solid rgba(166, 210, 255, 0.6)', borderRadius: '3px' });
    button.addEventListener("click", function () {
        const sb = [];
        let i = -1;
        const container = this.parentElement.parentElement;
        for(let index of container.children) {
            ++i;
            items = index.children[0];
            if (i==0) {
                sb.push(items.innerText.trim(), "\n", index.children[1].innerText.trim(), "\n\n");
                continue;
            }
            if (!items) continue;
            if (!items.children[0] || !items.children[1]) continue;
            sb.push(items.children[0].innerText.trim(), " -> ", items.children[1].innerText.trim(), "\n");
            if (!items.children[1].href) continue;
            sb.push("链接： ", items.children[1].href, "\n\n");
        }
        const result = sb.join("").trim();
        navigator.clipboard.writeText(result);
        isCopy();
    });
    button.addEventListener("mouseover", function () { Object.assign(button.style, { color: 'white', background: "rgba(166, 210, 255, 0.3)" }); });
    button.addEventListener("mouseout", function () { Object.assign(button.style, { color: 'rgb(166, 210, 255)', background: "white" }); });
    bottom.appendChild(button);
  }
  let i = 0;
  const container = getID("preparatory_committee");
  for(let index of container.children) {
    Object.assign(index.style, { margin: "80px 0px 80px 0px", padding: "0px 0px 0px 0px", border: '1px solid rgba(166, 210, 255, 0.6)', borderRadius: '6px' });
    for(let board of index.children) {
      const match = board.innerHTML.match(/[|]/);
      const tp = board.getAttribute("type");
      if (!match || !tp) continue;
      const muls = tp.match(/[|]/);
      const elm = createTag("div");
      renderer(board, elm, muls? tp.substring(0, muls.index) : tp, board.innerHTML.substring(0, match.index), board.innerHTML.substring(match.index + 1));
      Object.assign(elm.style, { display: 'flex' });
      Object.assign(board.style, { margin: '0px 0px 0px 0px', padding: '7px 20px 3px 20px', display: 'block' });
      if ((i&1) == 1) board.style.background = "rgba(80, 0, 255, 0.04)";
      board.appendChild(elm);
      ++i;
    }
    const header = createTag("div");
    cc_header(header, index.getAttribute("doc_title"), index.getAttribute("lastModifier"));
    index.insertBefore(header, index.firstChild);
    const bottom = createTag("div");
    cc_bottom(bottom);
    index.appendChild(bottom);
    window.addEventListener("orientationchange", function () {
        orientation(index.style, window.matchMedia("(orientation: portrait)").matches, { margin: "80px 0px 80px 0px" }, { margin: "80px 50px 80px 50px" });
    });
  }
}
