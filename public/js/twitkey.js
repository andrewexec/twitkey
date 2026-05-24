(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const jsonFetch = async (url, options = {}) => {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        headers.set('X-Requested-With', 'fetch');
        if (csrf) {
            headers.set('X-CSRF-Token', csrf);
        }
        const response = await fetch(url, { ...options, headers });
        const data = await response.json().catch(() => ({ ok: false, error: 'Invalid server response.' }));
        if (!response.ok || data.ok === false) {
            throw new Error(data.error || 'Request failed.');
        }
        return data;
    };

    const setupCounter = (textarea) => {
        const target = document.querySelector(textarea.dataset.counterTarget || '');
        const form = textarea.closest('form');
        const button = form?.querySelector('button[type="submit"]');
        const update = () => {
            const remaining = 140 - textarea.value.length;
            if (target) {
                target.textContent = String(remaining);
                target.classList.toggle('danger', remaining <= 20);
            }
            if (button) {
                button.disabled = remaining < 0 || textarea.value.trim().length === 0;
            }
        };
        textarea.addEventListener('input', update);
        update();
    };

    document.querySelectorAll('textarea[data-counter-target]').forEach(setupCounter);

    document.querySelectorAll('[data-tweet-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            if (!window.fetch) {
                return;
            }
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            try {
                const data = await jsonFetch(form.action, { method: 'POST', body: new FormData(form) });
                const timeline = document.querySelector('#timeline');
                if (timeline && data.html) {
                    timeline.insertAdjacentHTML('afterbegin', data.html);
                    wireDynamic(timeline.firstElementChild);
                }
                form.reset();
                form.querySelectorAll('textarea[data-counter-target]').forEach((textarea) => textarea.dispatchEvent(new Event('input')));
            } catch (error) {
                alert(error.message);
            } finally {
                button.disabled = false;
            }
        });
    });

    const wireFollow = (root = document) => {
        root.querySelectorAll('[data-follow-form]').forEach((form) => {
            if (form.dataset.bound) {
                return;
            }
            form.dataset.bound = '1';
            form.addEventListener('submit', async (event) => {
                if (!window.fetch) {
                    return;
                }
                event.preventDefault();
                const button = form.querySelector('button');
                const old = button.textContent;
                button.disabled = true;
                try {
                    const data = await jsonFetch(form.action, { method: 'POST', body: new FormData(form) });
                    button.textContent = data.following ? 'Unfollow' : 'Follow';
                } catch (error) {
                    button.textContent = old;
                    alert(error.message);
                } finally {
                    button.disabled = false;
                }
            });
        });
    };

    const wireFavorite = (root = document) => {
        root.querySelectorAll('[data-favorite-form]').forEach((form) => {
            if (form.dataset.bound) {
                return;
            }
            form.dataset.bound = '1';
            form.addEventListener('submit', async (event) => {
                if (!window.fetch) {
                    return;
                }
                event.preventDefault();
                const row = form.closest('.tweet-row');
                const button = form.querySelector('button');
                try {
                    const data = await jsonFetch(form.action, { method: 'POST', body: new FormData(form) });
                    button.textContent = data.favorited ? 'favorited' : 'favorite';
                    button.classList.toggle('is-favorited', Boolean(data.favorited));
                    const count = row?.querySelector('.favorite-count');
                    if (count) {
                        count.textContent = String(data.count);
                    }
                } catch (error) {
                    alert(error.message);
                }
            });
        });
    };

    const wireRetweet = (root = document) => {
        root.querySelectorAll('[data-retweet-form]').forEach((form) => {
            if (form.dataset.bound) {
                return;
            }
            form.dataset.bound = '1';
            form.addEventListener('submit', async (event) => {
                if (!window.fetch) {
                    return;
                }
                event.preventDefault();
                try {
                    const data = await jsonFetch(form.action, { method: 'POST', body: new FormData(form) });
                    const timeline = document.querySelector('#timeline');
                    if (timeline && data.html) {
                        timeline.insertAdjacentHTML('afterbegin', data.html);
                        wireDynamic(timeline.firstElementChild);
                    }
                } catch (error) {
                    alert(error.message);
                }
            });
        });
    };

    const wireReply = (root = document) => {
        root.querySelectorAll('[data-reply-toggle]').forEach((link) => {
            if (link.dataset.bound) {
                return;
            }
            link.dataset.bound = '1';
            link.addEventListener('click', (event) => {
                const row = link.closest('.tweet-row');
                const form = row?.querySelector('[data-reply-form]');
                if (!form) {
                    return;
                }
                event.preventDefault();
                form.classList.toggle('open');
                const textarea = form.querySelector('textarea');
                if (form.classList.contains('open') && textarea) {
                    textarea.focus();
                }
            });
        });

        root.querySelectorAll('[data-reply-form]').forEach((form) => {
            if (form.dataset.bound) {
                return;
            }
            form.dataset.bound = '1';
            form.addEventListener('submit', async (event) => {
                if (!window.fetch) {
                    return;
                }
                event.preventDefault();
                const button = form.querySelector('button[type="submit"]');
                const oldText = button ? button.textContent : '';
                if (button) {
                    button.disabled = true;
                    button.textContent = 'posting...';
                }
                try {
                    const data = await jsonFetch(form.action, { method: 'POST', body: new FormData(form) });
                    const row = form.closest('.tweet-row');
                    if (row && data.html) {
                        row.insertAdjacentHTML('afterend', data.html);
                        wireDynamic(row.nextElementSibling);
                        const count = row.querySelector('.reply-count');
                        if (count) {
                            count.textContent = String(Number(count.textContent || '0') + 1);
                        }
                    }
                    form.reset();
                    form.classList.remove('open');
                } catch (error) {
                    alert(error.message);
                } finally {
                    if (button) {
                        button.disabled = false;
                        button.textContent = oldText;
                    }
                }
            });
        });
    };

    const wireDelete = (root = document) => {
        root.querySelectorAll('[data-delete-url]').forEach((button) => {
            if (button.dataset.bound) {
                return;
            }
            button.dataset.bound = '1';
            button.addEventListener('click', async () => {
                if (!confirm('Delete this tweet?')) {
                    return;
                }
                try {
                    await jsonFetch(button.dataset.deleteUrl, { method: 'DELETE' });
                    button.closest('.tweet-row')?.remove();
                } catch (error) {
                    alert(error.message);
                }
            });
        });
    };

    const wireConfirm = (root = document) => {
        root.querySelectorAll('[data-confirm]').forEach((button) => {
            if (button.dataset.bound) {
                return;
            }
            button.dataset.bound = '1';
            button.addEventListener('click', (event) => {
                if (!confirm(button.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        });
    };

    const wireDynamic = (root = document) => {
        if (!root) {
            return;
        }
        wireFollow(root);
        wireFavorite(root);
        wireRetweet(root);
        wireReply(root);
        wireDelete(root);
        wireConfirm(root);
    };

    wireDynamic(document);

    document.querySelectorAll('[data-note-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const note = button.closest('[data-community-note]');
            const collapsed = note.classList.toggle('is-collapsed');
            button.textContent = collapsed ? '▶ show' : '▼ hide';
        });
    });

    const dmThread = document.querySelector('[data-dm-thread]');
    if (dmThread) {
        dmThread.scrollTop = dmThread.scrollHeight;
    }

    const usernameInput = document.querySelector('[data-username-check]');
    if (usernameInput) {
        let timer = null;
        const status = document.querySelector('.username-status');
        usernameInput.addEventListener('input', () => {
            clearTimeout(timer);
            const value = usernameInput.value.trim();
            status.textContent = '';
            status.className = 'field-error username-status';
            if (!value) {
                return;
            }
            timer = setTimeout(async () => {
                try {
                    const data = await jsonFetch(`/api/username?username=${encodeURIComponent(value)}`);
                    status.textContent = data.available ? '✓ username available' : '✗ username unavailable';
                    status.classList.add(data.available ? 'ok' : 'bad');
                } catch {
                    status.textContent = 'Could not check username.';
                    status.classList.add('bad');
                }
            }, 500);
        });
    }

    document.querySelectorAll('textarea').forEach((textarea) => {
        let box = null;
        const closeBox = () => {
            box?.remove();
            box = null;
        };
        textarea.addEventListener('input', async () => {
            const cursor = textarea.selectionStart || 0;
            const prefix = textarea.value.slice(0, cursor);
            const match = prefix.match(/(^|\s)([@#])([A-Za-z0-9_]{1,20})$/);
            if (!match) {
                closeBox();
                return;
            }
            const type = match[2];
            const query = match[3];
            try {
                const data = await jsonFetch(`/api/suggest?type=${encodeURIComponent(type)}&q=${encodeURIComponent(query)}`);
                closeBox();
                if (!data.items || data.items.length === 0) {
                    return;
                }
                box = document.createElement('div');
                box.className = 'autocomplete-box';
                data.items.forEach((item) => {
                    const value = item.value || item.tag;
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.textContent = type + value;
                    option.addEventListener('click', () => {
                        const start = cursor - query.length - 1;
                        textarea.value = textarea.value.slice(0, start) + type + value + ' ' + textarea.value.slice(cursor);
                        textarea.focus();
                        closeBox();
                        textarea.dispatchEvent(new Event('input'));
                    });
                    box.appendChild(option);
                });
                const rect = textarea.getBoundingClientRect();
                box.style.left = `${rect.left + window.scrollX}px`;
                box.style.top = `${rect.bottom + window.scrollY}px`;
                document.body.appendChild(box);
            } catch {
                closeBox();
            }
        });
        textarea.addEventListener('blur', () => setTimeout(closeBox, 150));
    });
})();
