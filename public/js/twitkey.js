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
                button.disabled = remaining < 0;
            }
        };
        textarea.addEventListener('input', update);
        update();
    };

    document.querySelectorAll('textarea[data-counter-target]').forEach(setupCounter);

    const resetComposeExtras = (form) => {
        form.querySelectorAll('[data-compose-panel]').forEach((panel) => panel.classList.remove('open'));
        form.querySelectorAll('[data-panel-toggle]').forEach((button) => {
            button.classList.remove('active');
            if (button.dataset.defaultLabel) {
                button.lastChild.textContent = button.dataset.defaultLabel;
            }
        });
        const attachmentButton = form.querySelector('[data-attachment-button]');
        if (attachmentButton?.dataset.defaultLabel) {
            attachmentButton.lastChild.textContent = attachmentButton.dataset.defaultLabel;
        }
        form.querySelectorAll('[data-gif-results], [data-location-results]').forEach((target) => {
            target.textContent = '';
        });
        form.querySelectorAll('[data-gif-url], [data-location-lat], [data-location-lng], [data-location-label]').forEach((input) => {
            input.value = '';
        });
        const selectedLocation = form.querySelector('[data-selected-location]');
        if (selectedLocation) {
            selectedLocation.textContent = 'No location selected.';
        }
        const pin = form.querySelector('[data-map-pin]');
        if (pin) {
            pin.style.display = 'none';
        }
    };

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
                if (data.scheduled) {
                    alert('Post scheduled. It will appear when the selected time arrives.');
                } else if (timeline && data.html) {
                    timeline.insertAdjacentHTML('afterbegin', data.html);
                    wireDynamic(timeline.firstElementChild);
                }
                form.reset();
                resetComposeExtras(form);
                form.querySelectorAll('textarea[data-counter-target]').forEach((textarea) => textarea.dispatchEvent(new Event('input')));
            } catch (error) {
                alert(error.message);
            } finally {
                button.disabled = false;
            }
        });
    });

    const wirePoll = (root = document) => {
        root.querySelectorAll('[data-poll-form]').forEach((form) => {
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
                const rowId = row?.id || '';
                try {
                    const data = await jsonFetch(form.action, { method: 'POST', body: new FormData(form) });
                    if (row && data.html) {
                        row.outerHTML = data.html;
                        const replacement = rowId ? document.getElementById(rowId) : null;
                        wireDynamic(replacement || document);
                    }
                } catch (error) {
                    alert(error.message);
                }
            });
        });
    };

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
                    button.textContent = data.pending ? 'Requested' : (data.following ? 'Unfollow' : 'Follow');
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
        wirePoll(root);
    };

    wireDynamic(document);

    document.querySelectorAll('[data-panel-toggle]').forEach((button) => {
        button.dataset.defaultLabel = button.lastChild.textContent;
        button.addEventListener('click', () => {
            const form = button.closest('form');
            const name = button.dataset.panelToggle;
            const panel = form?.querySelector(`[data-compose-panel="${name}"]`);
            if (!form || !panel) {
                return;
            }
            const open = !panel.classList.contains('open');
            form.querySelectorAll('[data-compose-panel]').forEach((item) => item.classList.remove('open'));
            form.querySelectorAll('[data-panel-toggle]').forEach((toggle) => toggle.classList.remove('active'));
            panel.classList.toggle('open', open);
            button.classList.toggle('active', open);
        });
    });

    document.querySelectorAll('[data-attachment-button]').forEach((button) => {
        button.dataset.defaultLabel = button.lastChild.textContent;
        button.addEventListener('click', () => {
            button.closest('form')?.querySelector('[data-attachment-input]')?.click();
        });
    });

    document.querySelectorAll('[data-attachment-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const button = input.closest('form')?.querySelector('[data-attachment-button]');
            if (!button) {
                return;
            }
            const count = input.files ? input.files.length : 0;
            button.lastChild.textContent = count > 0 ? `${count} file${count === 1 ? '' : 's'}` : (button.dataset.defaultLabel || 'Attachment');
        });
    });

    document.querySelectorAll('[data-gif-search]').forEach((button) => {
        button.addEventListener('click', async () => {
            const form = button.closest('form');
            const input = form?.querySelector('[data-gif-query]');
            const results = form?.querySelector('[data-gif-results]');
            const hidden = form?.querySelector('[data-gif-url]');
            const query = input?.value.trim() || '';
            if (!form || !input || !results || !hidden || query === '') {
                return;
            }
            results.textContent = 'Searching...';
            try {
                const data = await jsonFetch(`/api/gifs?q=${encodeURIComponent(query)}`);
                results.textContent = '';
                if (!data.items || data.items.length === 0) {
                    results.textContent = 'No GIFs found.';
                    return;
                }
                data.items.forEach((item) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.title = item.title || 'GIF';
                    const image = document.createElement('img');
                    image.src = item.url;
                    image.alt = item.title || 'GIF';
                    option.appendChild(image);
                    option.addEventListener('click', () => {
                        hidden.value = item.url;
                        const paste = form.querySelector('[data-gif-paste]');
                        if (paste) {
                            paste.value = item.url;
                        }
                        results.querySelectorAll('button').forEach((existing) => existing.classList.remove('selected'));
                        option.classList.add('selected');
                    });
                    results.appendChild(option);
                });
            } catch (error) {
                results.textContent = error.message;
            }
        });
    });

    document.querySelectorAll('[data-gif-paste]').forEach((input) => {
        input.addEventListener('input', () => {
            const hidden = input.closest('form')?.querySelector('[data-gif-url]');
            if (hidden) {
                hidden.value = input.value.trim();
            }
        });
    });

    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

    const setLocation = (form, lat, lng, label) => {
        const latitude = clamp(Number(lat), -90, 90);
        const longitude = clamp(Number(lng), -180, 180);
        form.querySelector('[data-location-lat]').value = latitude.toFixed(6);
        form.querySelector('[data-location-lng]').value = longitude.toFixed(6);
        form.querySelector('[data-location-label]').value = label;
        const selected = form.querySelector('[data-selected-location]');
        if (selected) {
            selected.textContent = label;
        }
        const pin = form.querySelector('[data-map-pin]');
        if (pin) {
            pin.style.display = 'block';
            pin.style.left = `${((longitude + 180) / 360) * 100}%`;
            pin.style.top = `${((90 - latitude) / 180) * 100}%`;
        }
    };

    document.querySelectorAll('[data-location-search]').forEach((button) => {
        button.addEventListener('click', async () => {
            const form = button.closest('form');
            const input = form?.querySelector('[data-location-query]');
            const results = form?.querySelector('[data-location-results]');
            const query = input?.value.trim() || '';
            if (!form || !input || !results || query === '') {
                return;
            }
            results.textContent = 'Searching...';
            try {
                const data = await jsonFetch(`/api/locations?q=${encodeURIComponent(query)}`);
                results.textContent = '';
                if (!data.items || data.items.length === 0) {
                    results.textContent = 'No places found.';
                    return;
                }
                data.items.forEach((item) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.textContent = item.label;
                    option.addEventListener('click', () => setLocation(form, item.lat, item.lng, item.label));
                    results.appendChild(option);
                });
            } catch (error) {
                results.textContent = error.message;
            }
        });
    });

    document.querySelectorAll('[data-map-picker]').forEach((picker) => {
        picker.addEventListener('click', (event) => {
            const form = picker.closest('form');
            if (!form) {
                return;
            }
            const rect = picker.getBoundingClientRect();
            const x = clamp((event.clientX - rect.left) / rect.width, 0, 1);
            const y = clamp((event.clientY - rect.top) / rect.height, 0, 1);
            const lat = 90 - (y * 180);
            const lng = (x * 360) - 180;
            setLocation(form, lat, lng, `${lat.toFixed(4)}, ${lng.toFixed(4)}`);
        });
    });

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

    const siteAlert = document.querySelector('[data-site-alert]');
    if (siteAlert) {
        const message = siteAlert.querySelector('[data-site-alert-message]');
        const refreshAlert = async () => {
            try {
                const data = await jsonFetch('/api/site_alert');
                const alert = data.alert;
                if (alert && alert.message) {
                    siteAlert.dataset.alertId = String(alert.id || '0');
                    if (message) {
                        message.textContent = alert.message;
                    }
                    siteAlert.classList.remove('hidden');
                } else {
                    siteAlert.dataset.alertId = '0';
                    if (message) {
                        message.textContent = '';
                    }
                    siteAlert.classList.add('hidden');
                }
            } catch {
                // Ignore transient polling failures; the next poll will retry.
            }
        };
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                refreshAlert();
            }
        });
        setInterval(refreshAlert, 10000);
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
