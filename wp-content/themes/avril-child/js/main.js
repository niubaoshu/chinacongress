if (self !== top) { top.location.href = self.location.href; }
const host = 'https://chinacongress.net';
const local = window.location.origin;
const theme = "avril-child";
const sessid=new URLSearchParams(window.location.search).get("sessid");
const cc = { local: local, host: host,
    session: sessid,
    uploads: `${host}/wp-content/uploads`,
    sgvdir: `${host}/wp-content/uploads/2026/08/`,
    cssdir: `${local}/wp-content/themes/${theme}/css`,
    jsdir: `${local}/wp-content/themes/${theme}/js`,
    committee_members: `${local}/archives/214`,
    social: {
        logo_128: "/2026/07/logo_128.png",
        media_url: "/2026/07/Social-media-2023_320320.png",
        icon: {
            facebook: [0, 0], x: [1, 0], instagram: [2, 0], tiktok: [3, 0], discord: [4, 0],
            snapchat: [0, 1], youtube: [1, 1], whatsapp: [2, 1], behance: [3, 1], thread: [4, 1],
            linkedln: [0, 2], dribbble: [1, 2], pinterest: [2, 2], twitch: [3, 2], telegram: [4, 2]
        }
    },
    links: [
      {
        img: '/2026/07/图片-13.png',
        url: 'https://t.me/+ncvykemkmQNmMjkx',
        title: '电报群： 中国议会 关注组'
      },
      {
        img: '/2026/07/x_logo.jpg',
        url: 'https://x.com/ChinaCongress',
        title: 'x账号： 中國議會（臨時）籌備委員會'
      },
      {
        img: '/2026/07/tiktok_logo.jpeg',
        url: 'https://www.tiktok.com/@chinacongress',
        title: 'Tiktok： 中国议会（临时）官方频道'
      },
      {
        img: '/2026/07/Youtube_logo.png',
        url: 'https://www.youtube.com/@ChinaCongressCommunications/videos',
        title: 'YouTube： 中国议会'
      }
    ],
    css_modules: {
        cc_main: "main.css",
    },
    js_modules: {
        cc_base: "cc_base.js",
        cc_video: "cc_video.js",
        appreciationCertificate: "appreciation_letter.js",
        annotation: "cc_annotation.js",
        congress_library: "congress_library.js",
        contact_details: "convenor_details.js",
        preparatory_committee: "preparatory_committee.js"
    },
    player: [],
    isMobile: /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent)
};
function getID(id) { return document.getElementById(id); }
function getTagName(name) { return document.getElementsByTagName(name); }
function getAttr(e, name) { return parseFloat(e.getAttribute(name), 10) || 1; }
function createTag(tag) { return document.createElement(tag); }
function orientation(style, matches, portrait, landscape) { Object.assign(style, matches? portrait:landscape); }
function isCopy() {
    const msg = createTag("div");
    msg.innerHTML="复制成功";
    Object.assign(msg.style,{ position:"fixed", top:"30%", left:"40%", transform:"translateX(-50%)", padding:"8px 16px", background:"rgba(0,0,0,.75)", color:"white", borderRadius:"4px", zIndex:"9999"});
    document.body.appendChild(msg);
    setTimeout(()=>msg.remove(),3000);
}
function session(url) { return url + '?sessid=' + cc.session + "&t=" + (window.cc_assets_ver || Date.now()); }
function cc_content(opt, isload, url, cc_args) {
    if (!isload || opt=="css") return;
    const func = new URL(url, location.href).pathname.split('/').pop().replace(/\.js$/, "");
    if (typeof window[func] === "function") {
        if (window[func].length == 0) { window[func](); }
        else if (cc_args != null) { window[func](...cc_args); }
    }
}
function lnCSS(url) {
    const css = createTag("link");
    Object.assign(css, { rel: 'stylesheet', href: session(url) });
    css.onload = () => { cc_content("css", true, url, null); };
    css.onerror = () => { cc_content("css", false, url, null); };
    document.head.appendChild(css);
}
function lnJS(url, cc_args) {
    const js = createTag("script");
    Object.assign(js, { type: 'text/javascript', src: session(url), referrerpolicy: 'no-referrer', fetchpriority:'high', charset: 'UTF-8', async: false });
    js.onload = () => { cc_content("js", true, url, cc_args); };
    js.onerror = () => { cc_content("js", false, url, null); };
    document.head.appendChild(js);
}
const cc_args = [cc.links, (typeof resp !== "undefined")? resp : null];
(function (cc_args) {
    if (self !== top) return;
    for (const [name, url] of Object.entries(cc.css_modules)) {
        lnCSS(`${cc.cssdir}/${url}`);
    }
    for (const [name, url] of Object.entries(cc.js_modules)) {
        if (!getID(name)) continue;
        lnJS(`${cc.jsdir}/${url}`, cc_args);
    }
    if (cc?.js_modules?.cc_base != null) {
        lnJS(`${cc.jsdir}/${cc.js_modules.cc_base}`, null);
    }
})(cc_args);
