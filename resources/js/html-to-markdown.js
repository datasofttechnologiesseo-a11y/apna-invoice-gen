/**
 * Convert pasted rich text (text/html) into Markdown.
 *
 * Why this exists: the blog editor is a Markdown textarea, and a textarea only
 * ever receives the text/plain half of a paste. So an author who writes in
 * Google Docs, Word or ChatGPT and pastes their article in loses every
 * heading, bold run, list and link - the post arrives as flat paragraphs. That
 * is the actual reason posts "have no headings", not the editor's toolbar,
 * which works fine for anyone typing Markdown by hand.
 *
 * Written by hand rather than pulling in Turndown: the page's CSP only admits
 * scripts from a short allowlist, and this is a few hundred lines against a
 * dependency plus a policy change.
 *
 * Exposed as window.htmlToMarkdown so the Alpine component in the blog editor
 * can call it from a Blade template.
 */

/** Characters that would otherwise be read as Markdown syntax. */
function escapeText(text) {
    return text
        .replace(/([\\`*_{}[\]()#+\-.!])/g, (m, ch, offset, s) => {
            // Only escape - . + # when they start a line, or they would turn
            // ordinary prose into a list or heading.
            if ('-.+#'.includes(ch)) {
                const lineStart = s.lastIndexOf('\n', offset - 1) + 1;
                const before = s.slice(lineStart, offset);
                return /^\s*$/.test(before) ? '\\' + ch : ch;
            }
            return '\\' + ch;
        });
}

/** Collapse runs of whitespace the way HTML rendering does. */
function collapse(text) {
    return text.replace(/[\t\n\r ]+/g, ' ');
}

const SKIP = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'HEAD', 'META', 'LINK', 'TITLE']);

/** Google Docs and Word express emphasis with inline styles, not tags. */
function styleOf(node) {
    const s = (node.getAttribute && node.getAttribute('style')) || '';
    const weight = /font-weight\s*:\s*(\d{3}|bold|bolder|normal)/i.exec(s);
    const italic = /font-style\s*:\s*italic/i.test(s);
    let bold = null;
    if (weight) {
        const v = weight[1].toLowerCase();
        bold = v === 'normal' ? false : (v === 'bold' || v === 'bolder' || Number(v) >= 600);
    }
    return { bold, italic };
}

/**
 * Walk a DOM node and emit Markdown.
 *
 * `ctx.listStack` tracks nested lists so ordered numbering and indentation
 * survive; `ctx.inPre` stops us escaping inside code blocks.
 */
function walk(node, ctx) {
    if (node.nodeType === Node.TEXT_NODE) {
        const raw = node.textContent || '';
        if (ctx.inPre) return raw;
        const text = collapse(raw);
        if (text.trim() === '' && text.indexOf(' ') === -1) return '';
        return ctx.noEscape ? text : escapeText(text);
    }

    if (node.nodeType !== Node.ELEMENT_NODE) return '';
    const tag = node.tagName;
    if (SKIP.has(tag)) return '';
    if (node.getAttribute && node.getAttribute('aria-hidden') === 'true') return '';

    const children = () => Array.from(node.childNodes).map((n) => walk(n, ctx)).join('');

    switch (tag) {
        case 'H1': case 'H2': case 'H3': case 'H4': case 'H5': case 'H6': {
            // The post title is the page's only H1, so a pasted H1 becomes an
            // H2 section heading - matching what Post::renderedBody() does
            // server-side, so the preview and the live page agree.
            const level = Math.min(6, Math.max(2, Number(tag[1]) === 1 ? 2 : Number(tag[1])));
            // Docs wraps heading text in a bold span, but a heading is already
            // bold - "## **Title**" just renders literal asterisks.
            const text = children().trim()
                .replace(/\s+/g, ' ')
                .replace(/^\*\*(.*)\*\*$/, '$1')
                .replace(/^\*(.*)\*$/, '$1');
            return text ? `\n\n${'#'.repeat(level)} ${text}\n\n` : '';
        }

        case 'P':
            return `\n\n${children().trim()}\n\n`;

        case 'BR':
            return '  \n';

        case 'HR':
            return '\n\n---\n\n';

        case 'STRONG': case 'B': {
            // Docs wraps the entire pasted document in
            // <b style="font-weight:normal">. Emitting ** for that makes the
            // whole article one bold run and swallows the real bold inside it.
            if (styleOf(node).bold === false) return children();
            const t = children().trim();
            return t ? `**${t}**` : '';
        }

        case 'EM': case 'I': {
            const t = children().trim();
            return t ? `*${t}*` : '';
        }

        case 'DEL': case 'S': case 'STRIKE': {
            const t = children().trim();
            return t ? `~~${t}~~` : '';
        }

        case 'CODE': {
            if (ctx.inPre) return children();
            const t = (node.textContent || '').trim();
            return t ? '`' + t.replace(/`/g, '') + '`' : '';
        }

        case 'PRE': {
            const wasPre = ctx.inPre;
            ctx.inPre = true;
            const body = (node.textContent || '').replace(/\n+$/, '');
            ctx.inPre = wasPre;
            return body ? `\n\n\`\`\`\n${body}\n\`\`\`\n\n` : '';
        }

        case 'A': {
            const href = (node.getAttribute('href') || '').trim();
            const text = children().trim();
            if (!text) return '';
            // Drop anchors that go nowhere useful, keeping their text.
            if (!href || href.startsWith('javascript:') || href.startsWith('#')) return text;
            return `[${text}](${href})`;
        }

        case 'IMG': {
            const src = (node.getAttribute('src') || '').trim();
            // A data: URI would paste megabytes of base64 into the body. Skip
            // it and let the author use the Image button, which uploads.
            if (!src || src.startsWith('data:')) return '';
            const alt = (node.getAttribute('alt') || '').trim();
            return `![${alt}](${src})`;
        }

        case 'BLOCKQUOTE': {
            const inner = children().trim();
            if (!inner) return '';
            return '\n\n' + inner.split('\n').map((l) => `> ${l}`.trimEnd()).join('\n') + '\n\n';
        }

        case 'UL': case 'OL': {
            ctx.listStack.push({ ordered: tag === 'OL', index: 1 });
            // Only the <li> children matter; the newlines between them would
            // otherwise render as a space in front of every bullet.
            const inner = Array.from(node.children)
                .filter((c) => c.tagName === 'LI')
                .map((c) => walk(c, ctx))
                .join('');
            ctx.listStack.pop();
            const prefix = ctx.listStack.length ? '\n' : '\n\n';
            return prefix + inner.replace(/\n+$/, '') + (ctx.listStack.length ? '\n' : '\n\n');
        }

        case 'LI': {
            const list = ctx.listStack[ctx.listStack.length - 1];
            if (!list) return children();
            const indent = '  '.repeat(Math.max(0, ctx.listStack.length - 1));
            const marker = list.ordered ? `${list.index++}. ` : '- ';
            // Nested lists come back already newline-prefixed; keep their
            // indentation and strip the blank lines a <p> inside an <li> adds.
            const body = children().replace(/\n{3,}/g, '\n\n').trim();
            const [first, ...rest] = body.split('\n');
            const restIndented = rest
                .map((l) => (l.trim() === '' ? '' : indent + '  ' + l.replace(/^\s+/, (s) => s)))
                .join('\n');
            return `${indent}${marker}${first}${rest.length ? '\n' + restIndented : ''}\n`;
        }

        case 'TABLE': {
            const rows = Array.from(node.querySelectorAll('tr'));
            if (!rows.length) return '';
            const cellsOf = (tr) => Array.from(tr.children)
                .filter((c) => c.tagName === 'TD' || c.tagName === 'TH')
                .map((c) => walk(c, ctx).replace(/\|/g, '\\|').replace(/\n+/g, ' ').trim());

            const head = cellsOf(rows[0]);
            if (!head.length) return '';
            const body = rows.slice(1).map(cellsOf).filter((r) => r.length);

            const line = (cells) => `| ${cells.join(' | ')} |`;
            const sep = `| ${head.map(() => '---').join(' | ')} |`;
            return `\n\n${line(head)}\n${sep}\n${body.map(line).join('\n')}\n\n`;
        }

        // Google Docs and Word wrap everything in these; pass through.
        case 'DIV': case 'SECTION': case 'ARTICLE': case 'MAIN': case 'SPAN':
        case 'BODY': case 'HTML': case 'FONT': case 'TBODY': case 'THEAD':
        case 'TR': case 'TD': case 'TH': case 'FIGURE': case 'FIGCAPTION':
        default: {
            // A styled span is how Docs and Word mark emphasis.
            if (tag === 'SPAN') {
                const { bold, italic } = styleOf(node);
                let inner = children();
                const trimmed = inner.trim();
                if (trimmed) {
                    if (bold === true) inner = inner.replace(trimmed, `**${trimmed}**`);
                    else if (italic) inner = inner.replace(trimmed, `*${trimmed}*`);
                }
                return inner;
            }
            const inner = children();
            // Block-level wrappers should not run their content together.
            const block = ['DIV', 'SECTION', 'ARTICLE', 'MAIN', 'FIGURE'].includes(tag);
            return block && inner.trim() ? `\n${inner}\n` : inner;
        }
    }
}

/**
 * @param {string} html  clipboard text/html
 * @returns {string} Markdown, or '' if the input had no usable content
 */
export function htmlToMarkdown(html) {
    if (!html || !html.trim()) return '';

    const doc = new DOMParser().parseFromString(html, 'text/html');
    if (!doc || !doc.body) return '';

    // Word and Google Docs ship comment scaffolding and empty spans.
    doc.body.querySelectorAll('style, script, meta, link, title').forEach((n) => n.remove());

    const ctx = { listStack: [], inPre: false, noEscape: false };
    let md = walk(doc.body, ctx);

    return md
        .replace(/ /g, ' ')          // Docs uses non-breaking spaces liberally
        .replace(/[ \t]+\n/g, '\n')       // trailing spaces, except the two that mean <br>
        .replace(/\n{3,}/g, '\n\n')       // at most one blank line between blocks
        .replace(/^\s+|\s+$/g, '')
        .trim();
}

if (typeof window !== 'undefined') {
    window.htmlToMarkdown = htmlToMarkdown;
}
