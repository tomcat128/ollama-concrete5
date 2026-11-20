/*
 * Chat for the "Ollama Chat" block. The conversation exists only in this page: the history is kept in a
 * variable and sent along with every question. Reloading or leaving the page starts a new memory.
 */
(function () {
    'use strict';

    function escapeHtml(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function inline(s) {
        // s is already HTML-escaped; inline code is kept verbatim, the rest gets bold/italic.
        return s.split(/(`[^`]+`)/g).map(function (part) {
            if (part.length > 1 && part.charAt(0) === '`' && part.charAt(part.length - 1) === '`') {
                return '<code>' + part.slice(1, -1) + '</code>';
            }
            return part.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/(^|[^*])\*(?!\s)([^*]+?)\*(?!\*)/g, '$1<em>$2</em>');
        }).join('');
    }

    // Minimal Markdown (code blocks, headings, lists, bold, italic, inline code). HTML is always escaped first.
    function renderMarkdown(src) {
        var blocks = [];
        var text = src.replace(/```[^\n`]*\n?([\s\S]*?)(```|$)/g, function (_, code) {
            blocks.push('<pre><code>' + escapeHtml(code.replace(/\n$/, '')) + '</code></pre>');
            return '\n\u0000' + (blocks.length - 1) + '\u0000\n';
        });
        text = escapeHtml(text);

        var html = '';
        var list = null;
        var para = [];
        function flushPara() {
            if (para.length) {
                html += '<p>' + inline(para.join('<br>')) + '</p>';
                para = [];
            }
        }
        function closeList() {
            if (list) {
                html += '</' + list + '>';
                list = null;
            }
        }
        function openList(type) {
            if (list !== type) {
                closeList();
                html += '<' + type + '>';
                list = type;
            }
        }

        text.split('\n').forEach(function (line) {
            var m;
            if (!line.trim()) {
                flushPara();
                closeList();
            } else if ((m = line.trim().match(/^\u0000(\d+)\u0000$/))) {
                flushPara();
                closeList();
                html += blocks[+m[1]];
            } else if ((m = line.match(/^(#{1,6})\s+(.*)$/))) {
                flushPara();
                closeList();
                html += '<p><strong>' + inline(m[2]) + '</strong></p>';
            } else if ((m = line.match(/^\s*[-*+]\s+(.*)$/))) {
                flushPara();
                openList('ul');
                html += '<li>' + inline(m[1]) + '</li>';
            } else if ((m = line.match(/^\s*\d+[.)]\s+(.*)$/))) {
                flushPara();
                openList('ol');
                html += '<li>' + inline(m[1]) + '</li>';
            } else {
                closeList();
                para.push(line);
            }
        });
        flushPara();
        closeList();
        return html;
    }

    function initChat(root) {
        var messagesEl = root.querySelector('.ollama-chat__messages');
        var form = root.querySelector('.ollama-chat__form');
        if (!messagesEl || !form) {
            return; // the user may not use the chat; the block only shows a notice
        }
        var input = root.querySelector('.ollama-chat__input');
        var sendBtn = root.querySelector('.ollama-chat__send');
        var resetBtn = root.querySelector('.ollama-chat__reset');
        var emptyHtml = messagesEl.innerHTML;
        var history = [];
        var busy = false;

        function scrollToBottom() {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function addMessage(role, content) {
            var empty = messagesEl.querySelector('.ollama-chat__empty');
            if (empty) {
                empty.remove();
            }
            var el = document.createElement('div');
            el.className = 'ollama-chat__msg ollama-chat__msg--' + role;
            if (role === 'assistant') {
                el.innerHTML = renderMarkdown(content);
            } else {
                el.textContent = content;
            }
            messagesEl.appendChild(el);
            scrollToBottom();
            return el;
        }

        function addThinking() {
            var el = addMessage('thinking', 'denkt nach …');
            var started = Date.now();
            var timer = setInterval(function () {
                el.textContent = 'denkt nach … ' + Math.round((Date.now() - started) / 1000) + ' s';
            }, 1000);
            return function () {
                clearInterval(timer);
                el.remove();
            };
        }

        function setBusy(value) {
            busy = value;
            sendBtn.disabled = value;
            resetBtn.disabled = value;
        }

        function ask(question) {
            setBusy(true);
            addMessage('user', question);
            var stopThinking = addThinking();
            fetch(root.dataset.askUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    token: root.dataset.token,
                    bID: Number(root.dataset.bid),
                    question: question,
                    history: history
                })
            }).then(function (res) {
                return res.json().catch(function () {
                    return { error: 'Unerwartete Antwort vom Server (HTTP ' + res.status + ').' };
                }).then(function (data) {
                    if (!res.ok || data.error) {
                        throw new Error(data.error || ('HTTP ' + res.status));
                    }
                    return data;
                });
            }).then(function (data) {
                stopThinking();
                addMessage('assistant', data.answer);
                // Only complete question/answer pairs are remembered, and only in this page.
                history.push({ role: 'user', content: question }, { role: 'assistant', content: data.answer });
            }).catch(function (error) {
                stopThinking();
                addMessage('error', error.message || String(error));
            }).then(function () {
                setBusy(false);
                input.focus();
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var question = input.value.trim();
            if (!question || busy) {
                return;
            }
            input.value = '';
            ask(question);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });

        resetBtn.addEventListener('click', function () {
            if (busy) {
                return;
            }
            history = [];
            messagesEl.innerHTML = emptyHtml;
            input.focus();
        });
    }

    function initAll() {
        document.querySelectorAll('.ollama-chat').forEach(initChat);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
