/* Эмодзи в стиле iPhone для чатов колл-центра (редизайн Nordika): объёмные картинки Microsoft Fluent Emoji 3D
   (лицензия MIT, пакет @lobehub/fluent-emoji-3d на jsDelivr; имя файла — коды символа через «-»).
   Набор для выбора с разделами и «Недавними», и показ эмодзи картинками в сообщениях. В поле ввода и в отправленном
   тексте остаются обычные символы Юникода — клиент в VK/Avito/Telegram видит свои эмодзи. */
window.ndEmoji = (() => {
    const CDN = 'https://cdn.jsdelivr.net/npm/@lobehub/fluent-emoji-3d@1.1.0/assets/';
    const RECENT_KEY = 'ndEmojiRecent';
    const CATS = [
        ['faces', 'Смайлы и люди', '😀 😃 😄 😁 😆 🥹 😅 😂 🤣 🥲 ☺️ 😊 😇 🙂 🙃 😉 😌 😍 🥰 😘 😗 😙 😚 😋 😛 😝 😜 🤪 🤨 🧐 🤓 😎 🥸 🤩 🥳 😏 😒 😞 😔 😟 😕 🙁 ☹️ 😣 😖 😫 😩 🥺 😢 😭 😤 😠 😡 🤬 🤯 😳 🥵 🥶 😱 😨 😰 😥 😓 🤗 🤔 🫣 🤭 🫢 🫡 🤫 🫠 🤥 😶 😐 😑 😬 🙄 😯 😦 😧 😮 😲 🥱 😴 🤤 😪 😵 🤐 🥴 🤢 🤮 🤧 😷 🤒 🤕 🤑 🤠 😈 👿 🤡 💩 👻 💀 👽 🤖 🎃 😺 😸 😹 😻 😼 😽 🙀 😿 😾'],
        ['hands', 'Жесты и люди', '👋 🤚 🖐️ ✋ 🖖 👌 🤌 🤏 ✌️ 🤞 🫰 🤟 🤘 🤙 👈 👉 👆 👇 ☝️ 👍 👎 ✊ 👊 🤛 🤜 👏 🙌 🫶 👐 🤲 🤝 🙏 ✍️ 💪 👀 🧠 👨‍🔧 👷 🙋‍♀️ 🙋‍♂️ 🤷‍♀️ 🤷‍♂️ 💁‍♀️ 🙆‍♀️ 🙅‍♀️'],
        ['hearts', 'Сердечки и знаки', '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 🤎 💔 ❤️‍🔥 💕 💞 💓 💗 💖 💘 💝 ✅ ☑️ ✔️ ❌ ❗ ❓ ‼️ ⚠️ 💯 🔥 ⭐ 🌟 ✨ ⚡ 💥 🎉 🎊 🎁 🏆 🥇 💬 💭 🔔 🆗 🆕 🔝'],
        ['work', 'Работа и дом', '📞 ☎️ 📱 💻 📩 ✉️ 📧 📅 🗓️ ⏰ ⌛ ⏳ 📍 🗺️ 🏠 🏡 🏢 🛠️ 🔧 🔨 📐 📏 🪜 💡 🔦 🛋️ 🛏️ 🚪 🪟 🧱 🎨 🖌️ 💳 💰 💵 🧾 📄 📝 📎 📌 🔑 🚗 🚚 📦 ☀️ 🌤️ 🌧️ ❄️ 🌙 ☕ 🍰 🍾 🌹 💐'],
    ].map(([key, title, list]) => ({ key, title, list: list.split(' ') }));

    const code = (e) => Array.from(e).map((c) => c.codePointAt(0).toString(16)).join('-');
    const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const img = (e, cls = 'nd-emo') => `<img class="${cls}" src="${CDN}${code(e)}.webp" alt="${esc(e)}" data-c="${code(e)}" draggable="false" loading="lazy">`;

    // картинки нет под таким именем: «❤» без fe0f → «❤️» и наоборот; совсем нет — остаётся обычный символ
    document.addEventListener('error', (ev) => {
        const im = ev.target;
        if (!(im instanceof HTMLImageElement) || !im.matches('img.nd-emo, img.nd-emo-pick')) return;
        const c = im.dataset.c || '';
        if (!im.dataset.retry && c) {
            im.dataset.retry = '1';
            const alt = c.includes('-fe0f') ? c.replace(/-fe0f/g, '') : (c.includes('-') ? '' : c + '-fe0f');
            if (alt) { im.src = CDN + alt + '.webp'; return; }
        }
        im.replaceWith(document.createTextNode(im.alt));
    }, true);

    // эмодзи в тексте: флаги, последовательности с ZWJ и оттенками кожи, обычные пиктограммы
    const RE = /\p{Regional_Indicator}{2}|\p{Extended_Pictographic}(?:️|\p{Emoji_Modifier})?(?:‍\p{Extended_Pictographic}(?:️|\p{Emoji_Modifier})?)*/gu;
    const ONLY = /^(?:\s*(?:\p{Regional_Indicator}{2}|\p{Extended_Pictographic}(?:️|\p{Emoji_Modifier})?(?:‍\p{Extended_Pictographic}(?:️|\p{Emoji_Modifier})?)*)\s*){1,3}$/u;

    /** Эмодзи в тексте элемента — картинками; сообщение из 1–3 эмодзи — крупно (класс nd-emo-only). */
    function decorate(root, jumboSel) {
        if (!root) return;
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) {
            RE.lastIndex = 0;
            if (RE.test(walker.currentNode.nodeValue)) nodes.push(walker.currentNode);
        }
        nodes.forEach((node) => {
            const text = node.nodeValue;
            const frag = document.createDocumentFragment();
            let last = 0;
            text.replace(RE, (m, offset) => {
                if (offset > last) frag.appendChild(document.createTextNode(text.slice(last, offset)));
                const tpl = document.createElement('template');
                tpl.innerHTML = img(m);
                frag.appendChild(tpl.content.firstChild);
                last = offset + m.length;
                return m;
            });
            if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
            node.replaceWith(frag);
        });
        if (jumboSel) {
            root.querySelectorAll(jumboSel).forEach((el) => {
                const t = Array.from(el.childNodes).map((n) => n.nodeType === 3 ? n.nodeValue : (n.alt || '')).join('');
                el.classList.toggle('nd-emo-only', ONLY.test(t));
            });
        }
    }

    const recent = () => { try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch (e) { return []; } };
    const remember = (e) => {
        const r = [e, ...recent().filter((x) => x !== e)].slice(0, 24);
        try { localStorage.setItem(RECENT_KEY, JSON.stringify(r)); } catch (err) {}
    };

    /** Набор для выбора в контейнере pop: вкладки разделов сверху, сетка с прокруткой; onPick(эмодзи). */
    function picker(pop, onPick) {
        const sections = [{ key: 'recent', title: 'Недавние', list: recent() }, ...CATS].filter((s) => s.list.length);
        const tabIcon = { recent: '🕘', faces: '😀', hands: '👋', hearts: '❤️', work: '🏠' };
        pop.innerHTML = '<div class="nd-emo-tabs">'
            + sections.map((s) => `<button type="button" data-tab="${s.key}" title="${esc(s.title)}">${img(tabIcon[s.key] || s.list[0], 'nd-emo-pick')}</button>`).join('')
            + '</div><div class="nd-emo-scroll">'
            + sections.map((s) => `<div class="nd-emo-sec" data-sec="${s.key}"><div class="nd-emo-h">${esc(s.title)}</div><div class="nd-emo-grid">`
                + s.list.map((e) => `<button type="button" data-emoji="${esc(e)}" title="${esc(e)}">${img(e, 'nd-emo-pick')}</button>`).join('')
                + '</div></div>').join('')
            + '</div>';
        const scroller = pop.querySelector('.nd-emo-scroll');
        pop.onclick = (ev) => {
            ev.stopPropagation();
            const tab = ev.target.closest('[data-tab]');
            if (tab) {
                const sec = pop.querySelector(`[data-sec="${tab.dataset.tab}"]`);
                if (sec) scroller.scrollTo({ top: sec.offsetTop - scroller.offsetTop, behavior: 'smooth' });
                return;
            }
            const b = ev.target.closest('[data-emoji]');
            if (!b) return;
            remember(b.dataset.emoji);
            onPick(b.dataset.emoji);
        };
    }

    return { decorate, picker, code };
})();
