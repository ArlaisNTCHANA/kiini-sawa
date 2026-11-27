/*
 * Écriture « comme dans Word » pour le studio de l'Académie (blocs paragraphe, liste, citation, encadré).
 *
 * L'auteur voit le gras en gras, l'italique en italique, les liens et les puces : jamais d'astérisque ni de tiret.
 * Le texte reste enregistré dans le même format qu'avant (Markdown simple, lu par LessonMarkdown côté serveur) :
 * ce module traduit dans les deux sens. Un bloc dont la mise en forme dépasse ce qu'il sait afficher (tableau, code…)
 * n'est pas converti : il reste modifiable en texte, pour ne jamais rien perdre (voir canEdit).
 *
 * Trois façons d'écrire :
 *  - « inline »  : un paragraphe ou une citation ; Entrée = retour à la ligne ;
 *  - « list »    : une liste, un point par puce ;
 *  - « doc »     : un encadré, avec paragraphes et listes.
 */
(function (root) {
    'use strict';

    // Caractères que Parsedown accepte d'échapper par « \ »
    const ESCAPABLE = '\\`*_{}[]()>#+-.!';
    const LIST_LINE = /^\s{0,3}(?:([*+-])|(\d{1,9})[.)])\s+(.*)$/;

    const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    /** Position de la fermeture `tok` à partir de `from` (en sautant les caractères échappés), ou -1. */
    function findClose(s, from, tok) {
        for (let i = from; i < s.length; i++) {
            if (s[i] === '\\') { i++; continue; }
            if (s.startsWith(tok, i)) {
                if (tok === '*' && s[i + 1] === '*') { // un « ** » à l'intérieur d'un italique : un gras imbriqué
                    const j = findClose(s, i + 2, '**');
                    if (j < 0) return -1;
                    i = j + 1;
                    continue;
                }
                return i;
            }
        }
        return -1;
    }

    function safeHref(url) {
        const u = url.trim();
        return /^(https?:\/\/|\/|mailto:|#)/i.test(u) ? u : null;
    }

    /** Markdown d'une ligne (ou de plusieurs, retours à la ligne compris) → HTML affiché dans l'éditeur. */
    function inlineToHtml(s) {
        let out = '';
        let i = 0;
        while (i < s.length) {
            const c = s[i];
            if (c === '\\' && i + 1 < s.length && ESCAPABLE.includes(s[i + 1])) { out += esc(s[i + 1]); i += 2; continue; }
            if (c === '\n') { out += '<br>'; i++; continue; }
            if (s.startsWith('**', i) && s[i + 2] && s[i + 2] !== ' ') {
                const j = findClose(s, i + 2, '**');
                if (j > i + 2) { out += '<strong>' + inlineToHtml(s.slice(i + 2, j)) + '</strong>'; i = j + 2; continue; }
            }
            if (c === '*' && s[i + 1] && s[i + 1] !== ' ' && s[i + 1] !== '*') {
                const j = findClose(s, i + 1, '*');
                if (j > i + 1) { out += '<em>' + inlineToHtml(s.slice(i + 1, j)) + '</em>'; i = j + 1; continue; }
            }
            if (c === '_' && (i === 0 || !/[\p{L}\p{N}]/u.test(s[i - 1])) && s[i + 1] && s[i + 1] !== ' ' && s[i + 1] !== '_') {
                const j = findClose(s, i + 1, '_');
                if (j > i + 1 && !/[\p{L}\p{N}]/u.test(s[j + 1] || '')) { out += '<em>' + inlineToHtml(s.slice(i + 1, j)) + '</em>'; i = j + 1; continue; }
            }
            if (c === '[') {
                const m = /^\[((?:\\.|[^\]\\])+)\]\(([^()\s]+)\)/.exec(s.slice(i));
                if (m && safeHref(m[2])) { out += '<a href="' + esc(m[2]) + '">' + inlineToHtml(m[1]) + '</a>'; i += m[0].length; continue; }
            }
            out += esc(c);
            i++;
        }
        return out;
    }

    function listToHtml(lines, ordered) {
        return (ordered ? '<ol>' : '<ul>') + lines.map((l) => '<li>' + (inlineToHtml(l) || '<br>') + '</li>').join('') + (ordered ? '</ol>' : '</ul>');
    }

    /** Markdown → HTML de l'éditeur, selon la façon d'écrire. */
    function toHtml(md, mode, ordered) {
        const text = String(md || '').replace(/\r\n?/g, '\n');
        if (mode === 'list') {
            const items = text.split('\n').map((l) => l.trim()).filter(Boolean);
            return listToHtml(items.length ? items : [''], ordered);
        }
        if (mode === 'inline') return inlineToHtml(text.trim());
        // doc : paragraphes séparés par une ligne vide ; une suite de lignes « - » ou « 1. » est une liste
        const parts = [];
        for (const chunk of text.trim().split(/\n{2,}/)) {
            const lines = chunk.split('\n');
            if (lines.every((l) => /^\s{0,3}>/.test(l))) {
                parts.push('<blockquote>' + inlineToHtml(lines.map((l) => l.replace(/^\s{0,3}> ?/, '')).join('\n')) + '</blockquote>');
                continue;
            }
            let para = [];
            let list = null;
            const flushPara = () => { if (para.length) parts.push('<p>' + inlineToHtml(para.join('\n')) + '</p>'); para = []; };
            const flushList = () => { if (list) parts.push(listToHtml(list.items, list.ordered)); list = null; };
            for (const line of chunk.split('\n')) {
                const m = LIST_LINE.exec(line);
                if (m) {
                    const isOrdered = m[2] !== undefined;
                    flushPara();
                    if (list && list.ordered !== isOrdered) flushList();
                    list = list || { ordered: isOrdered, items: [] };
                    list.items.push(m[3]);
                } else {
                    flushList();
                    para.push(line);
                }
            }
            flushPara();
            flushList();
        }
        return parts.join('') || '<p><br></p>';
    }

    // ---------- HTML de l'éditeur → Markdown ----------

    const escText = (t) => t.replace(/[\\`*_[\]]/g, (ch) => '\\' + ch);

    function isBold(el) {
        const tag = el.tagName;
        if (tag === 'STRONG' || tag === 'B') return true;
        const w = el.style && el.style.fontWeight;
        return w === 'bold' || parseInt(w, 10) >= 600;
    }
    function isItalic(el) {
        const tag = el.tagName;
        return tag === 'EM' || tag === 'I' || (el.style && el.style.fontStyle === 'italic');
    }

    /** Entoure de marques en laissant les espaces de bord à l'extérieur (« **mot** » et non « **mot ** »). */
    function wrapMark(inner, mark) {
        const m = /^(\s*)([\s\S]*?)(\s*)$/.exec(inner);
        if (!m[2]) return inner;
        return m[1] + mark + m[2] + mark + m[3];
    }

    function inlineMd(node) {
        let out = '';
        node.childNodes.forEach((child) => {
            if (child.nodeType === 3) { out += escText(child.nodeValue.replace(/ /g, ' ').replace(/[\t\n\r ]+/g, ' ')); return; }
            if (child.nodeType !== 1) return;
            const tag = child.tagName;
            if (tag === 'BR') { out += '\n'; return; }
            if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEMPLATE') return;
            let inner = inlineMd(child);
            if (tag === 'A') {
                const href = safeHref(child.getAttribute('href') || '');
                const label = inner.replace(/\n/g, ' ').trim();
                out += href && label ? '[' + label + '](' + href.replace(/\s/g, '%20').replace(/\(/g, '%28').replace(/\)/g, '%29') + ')' : inner;
                return;
            }
            if (isBold(child)) inner = wrapMark(inner, '**');
            if (isItalic(child)) inner = wrapMark(inner, '*');
            if (tag === 'DIV' || tag === 'P' || tag === 'LI' || /^H[1-6]$/.test(tag)) {
                if (out && !out.endsWith('\n')) out += '\n';
                out += inner;
                if (!inner.endsWith('\n')) out += '\n';
                return;
            }
            out += inner;
        });
        return out;
    }

    /** Une ligne qui commencerait un titre, une citation ou une liste est échappée : elle reste du texte. */
    function protectLines(md) {
        return md.split('\n').map((l) => l
            .replace(/^(\s*)([#>])/, '$1\\$2')
            .replace(/^(\s*)([+-])(\s)/, '$1\\$2$3')
            .replace(/^(\s*\d{1,9})([.)])(\s)/, '$1\\$2$3')).join('\n');
    }

    const cleanLines = (md) => md.split('\n').map((l) => l.replace(/[ \t]+$/, '').replace(/^ +/, '')).join('\n');

    function inlineFrom(node) {
        return protectLines(cleanLines(inlineMd(node)).replace(/\n{2,}/g, '\n').replace(/^\n+|\n+$/g, ''));
    }

    function itemsFrom(listEl) {
        const items = [];
        listEl.querySelectorAll(':scope > li').forEach((li) => {
            const t = protectLines(cleanLines(inlineMd(li)).replace(/\n+/g, ' ').trim());
            if (t) items.push(t);
        });
        return items;
    }

    /** HTML de l'éditeur → Markdown, selon la façon d'écrire. */
    function toMarkdown(el, mode) {
        if (mode === 'inline') return inlineFrom(el);
        if (mode === 'list') {
            const list = el.querySelector('ul, ol');
            return list ? itemsFrom(list).join('\n') : inlineFrom(el).split('\n').map((l) => l.trim()).filter(Boolean).join('\n');
        }
        // doc
        const parts = [];
        let loose = null; // texte posé directement dans l'encadré, sans paragraphe
        const flushLoose = () => { if (loose) { const t = inlineFrom(loose); if (t) parts.push(t); } loose = null; };
        el.childNodes.forEach((child) => {
            const tag = child.nodeType === 1 ? child.tagName : '';
            if (tag === 'UL' || tag === 'OL') {
                flushLoose();
                const items = itemsFrom(child);
                if (items.length) parts.push(items.map((t, k) => (tag === 'OL' ? (k + 1) + '. ' : '- ') + t).join('\n'));
            } else if (tag === 'BLOCKQUOTE') {
                flushLoose();
                const t = inlineFrom(child);
                if (t) parts.push(t.split('\n').map((l) => '> ' + l).join('\n'));
            } else if (tag === 'P' || tag === 'DIV' || /^H[1-6]$/.test(tag)) {
                flushLoose();
                const nested = child.querySelector('ul, ol');
                if (nested) { parts.push(toMarkdown(child, 'doc')); return; }
                const t = inlineFrom(child);
                if (t) parts.push(t);
            } else {
                loose = loose || child.ownerDocument.createElement('div');
                loose.appendChild(child.cloneNode(true));
            }
        });
        flushLoose();
        return parts.filter(Boolean).join('\n\n');
    }

    /**
     * Le bloc peut-il s'écrire « comme dans Word » sans rien perdre ? Oui si le texte revient identique, ou si la
     * différence ne change rien à l'affichage (une ligne vide de plus avant une liste, un « * » de liste devenu « - »…).
     */
    function canEdit(md, mode, doc, ordered) {
        const source = String(md || '').replace(/\r\n?/g, '\n').trim();
        if (source === '') return true;
        if (mode === 'doc' && /^ {4,}|\t|^\|.*\|/m.test(source)) return false; // code, tableaux
        if (/<[a-z!/]|`|~~|!\[|\[\[|^:::/im.test(source)) return false; // HTML, code, barré, images, schémas, encadrés imbriqués
        if (/^\s{0,3}#/m.test(source)) return false; // un titre dans le bloc
        if (mode !== 'doc' && /^\s{0,3}(>|[*+-]\s|\d{1,9}[.)]\s)/m.test(source)) return false; // citation ou liste dans un paragraphe
        if (mode === 'doc' && source.split(/\n{2,}/).some((c) => /^\s{0,3}>/m.test(c) && !c.split('\n').every((l) => /^\s{0,3}>/.test(l)))) return false; // citation mêlée à du texte
        const box = (doc || root.document).createElement('div');
        box.innerHTML = toHtml(source, mode, ordered);
        const back = toMarkdown(box, mode);
        if (back === source) return true;
        return toHtml(back, mode, ordered) === toHtml(source, mode, ordered);
    }

    // ---------- Branchement dans la page ----------

    /** Pose le texte dans la zone et la tient à jour : `onChange(markdown)` à chaque frappe. */
    function mount(el, md, mode, onChange, ordered) {
        const doc = el.ownerDocument;
        el.innerHTML = toHtml(md, mode, ordered);
        const emit = () => {
            if (mode === 'list' && !el.querySelector('li')) { el.innerHTML = listToHtml([''], ordered); placeCaretEnd(el.querySelector('li')); }
            onChange(toMarkdown(el, mode));
        };
        el.addEventListener('input', emit);
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && mode === 'inline') { e.preventDefault(); doc.execCommand('insertLineBreak'); emit(); }
            if (e.key === 'Enter' && mode === 'list' && e.shiftKey) { e.preventDefault(); }
        });
        // Coller depuis Word, une page web ou un e-mail : on garde gras, italique, liens et listes, rien d'autre
        el.addEventListener('paste', (e) => {
            const data = e.clipboardData;
            if (!data) return;
            e.preventDefault();
            const html = data.getData('text/html');
            let md;
            if (html) {
                const parsed = new DOMParser().parseFromString(html, 'text/html');
                parsed.querySelectorAll('style, script, meta, title, xml').forEach((n) => n.remove());
                md = toMarkdown(parsed.body, mode === 'list' ? 'list' : (mode === 'doc' ? 'doc' : 'inline'));
            } else {
                md = escText(data.getData('text/plain') || '');
                if (mode !== 'doc') md = protectLines(md.replace(/\r\n?/g, '\n'));
            }
            if (mode === 'list') {
                // Chaque ligne collée devient un point de la liste
                const items = md.split('\n').map((l) => l.trim()).filter(Boolean);
                doc.execCommand('insertHTML', false, items.map((t, k) => (k ? '</li><li>' : '') + inlineToHtml(t)).join(''));
            } else if (mode === 'inline') {
                doc.execCommand('insertHTML', false, inlineToHtml(md.replace(/\n{2,}/g, '\n')));
            } else {
                doc.execCommand('insertHTML', false, toHtml(md, 'doc'));
            }
            emit();
        });
        return { refresh(newOrdered) { ordered = newOrdered; el.innerHTML = toHtml(toMarkdown(el, mode), mode, ordered); emit(); } };
    }

    function placeCaretEnd(node) {
        if (!node) return;
        const doc = node.ownerDocument;
        const range = doc.createRange();
        range.selectNodeContents(node);
        range.collapse(false);
        const sel = doc.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }

    /** Boutons de mise en forme : la sélection reste dans la zone (boutons en @mousedown.prevent). */
    function format(el, command) {
        const doc = el.ownerDocument;
        if (!el.contains(doc.getSelection().anchorNode)) el.focus();
        if (command === 'link') {
            const sel = doc.getSelection();
            const inLink = sel.anchorNode && (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement).closest('a');
            if (inLink && el.contains(inLink)) { doc.execCommand('unlink'); }
            else {
                if (sel.isCollapsed) { alert('Sélectionnez d\'abord les mots à transformer en lien.'); return; }
                const url = prompt('Adresse du lien (https://… ou /methode/…)');
                if (!url) return;
                if (!safeHref(url)) { alert('L\'adresse doit commencer par https://, http:// ou /.'); return; }
                doc.execCommand('createLink', false, url.trim());
            }
        } else if (command === 'quote') {
            const sel = doc.getSelection();
            const node = sel.anchorNode && (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement);
            doc.execCommand('formatBlock', false, node && node.closest('blockquote') ? 'p' : 'blockquote');
        } else {
            doc.execCommand(command);
        }
        el.dispatchEvent(new Event('input'));
    }

    const api = { toHtml, toMarkdown, canEdit, mount, format, inlineToHtml };
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.KsRich = api;
})(typeof window !== 'undefined' ? window : globalThis);
