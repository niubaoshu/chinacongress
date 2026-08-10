function congress_library() {
    function prop(num,q){
        let s=createTag("span");
        Object.assign(s.style,{position:'absolute',right:'50px',background:`url("${cc.uploads}/2026/07/congress-250.png") center/cover no-repeat`,backgroundSize:'100px auto',transform:'translateY(-23%)',width:'100px',height:'35px',zIndex:10,opacity:.15});
        q.insertBefore(s,q.firstChild);

        s=createTag("span");
        Object.assign(s.style,{position:'absolute',left:'32px',background:`url("${cc.uploads}/2026/07/logo_128.png") center/cover no-repeat`,transform:'translateY(-25%)',width:'35px',height:'35px',zIndex:10,opacity:.6});
        q.insertBefore(s,q.firstChild);

        s=createTag("span");
        Object.assign(s.style,{position:'absolute',transform:'translateY(-3%)',fontWeight:'bold',fontSize:'18px',textAlign:'right',right:'35px',width:'35px',height:'35px',zIndex:10,opacity:.3});
        s.innerHTML=num;
        q.insertBefore(s,q.firstChild);
    }

    function reload(body,e) {
        let a=e.innerHTML.split(/\\n/);
        e.innerHTML="";
        a.forEach(l=>{
            if(body){
                let d=document.createElement("div"),c=createTag("span");
                Object.assign(d.style,{fontWeight:'350',fontSize:'18px',textIndent:'2em',padding:'12px 0 0'});
                d.innerHTML=l;
                d.insertBefore(c,d.firstChild);
                e.appendChild(d);
            }else e.appendChild(document.createTextNode(l));
        });
    }

    function load(n,q,a) {
        let c="rgb(225,225,225)";
        Object.assign(q.style,{margin:'5px 5px 0',color:'rgb(0,0,35)',fontWeight:'bold',fontSize:'18px',borderRadius:'8px 8px 0 0',border:`1px solid ${c}`,borderBottom:`1px dotted ${c}`,padding:'20px 30px 5px',backgroundColor:'rgb(235,245,255)'});
        Object.assign(a.style,{borderRadius:'0 0 8px 8px',border:`1px solid ${c}`,borderTop:0,margin:'0 5px 60px',padding:'10px',lineHeight:1.5,fontSize:'15px',backgroundColor:'rgb(252,252,255)'});
        reload(0,q);
        reload(1,a);
        prop(n,q);
    }

    let n=1,r=getID("congress_library");
    for(let e of r.children)
        if(e.children.length==2)load(n++,e.children[0],e.children[1]);
    Object.assign(r.style,{display:'block',visibility:'visible'});
}
