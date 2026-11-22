/**
 * Writing help for blog posts in the block editor (modules/ai-post-tools.php, 2026-11-17).
 * A "Writing help" panel in the post's sidebar (alt text for the pictures, tags, category, excerpt, a caption to share, an artwork card) and a
 * "Before you publish" list. Nothing is saved by itself: every answer lands in a field you can edit and is saved with the post.
 */
(function (wp, cfg) {
    var el = wp.element.createElement, useState = wp.element.useState, Fragment = wp.element.Fragment;
    var C = wp.components, data = wp.data, apiFetch = wp.apiFetch;
    var Panel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
    var PrePublish = (wp.editor && wp.editor.PluginPrePublishPanel) || (wp.editPost && wp.editPost.PluginPrePublishPanel);
    var STORE = 'core/editor', BLOCKS = 'core/block-editor';
    if (!Panel || !wp.plugins) return;

    /* ---------- what the editor has now ---------- */
    function flatten(blocks) {
        var out = [];
        (blocks || []).forEach(function (b) {
            if (b.name === 'core/image') out.push(b);
            if (b.innerBlocks && b.innerBlocks.length) out = out.concat(flatten(b.innerBlocks));
        });
        return out;
    }
    function attr(name) { return data.select(STORE).getEditedPostAttribute(name); }
    function ctx(extra) {
        var imgs = flatten(data.select(BLOCKS).getBlocks()).filter(function (b) { return b.attributes.id; });
        var o = {
            post_id: data.select(STORE).getCurrentPostId() || 0,
            title: attr('title') || '',
            content: data.select(STORE).getEditedPostContent() || '',
            tag_ids: attr('tags') || [],
            cat_ids: attr('categories') || [],
            image_id: (imgs[0] && imgs[0].attributes.id) || attr('featured_media') || 0
        };
        for (var k in (extra || {})) o[k] = extra[k];
        return o;
    }

    /* ---------- the calls ---------- */
    function call(action, extra) {
        var fd = new FormData(), o = ctx(extra);
        fd.append('action', action);
        fd.append('_ajax_nonce', cfg.nonce);
        Object.keys(o).forEach(function (k) {
            if (Array.isArray(o[k])) o[k].forEach(function (v) { fd.append(k + '[]', v); });
            else fd.append(k, o[k]);
        });
        return fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) { if (j && j.success) return j.data; throw new Error((j && j.data) || 'Something went wrong.'); });
    }

    /* Library alt texts waiting for the post to be saved (the block's alt is the post's; the Media Library's is written on save) */
    var pending = {};
    var wasSaving = false;
    data.subscribe(function () {
        var s = data.select(STORE), saving = s.isSavingPost() && !s.isAutosavingPost();
        if (wasSaving && !saving && s.didPostSaveRequestSucceed()) {
            Object.keys(pending).forEach(function (id) {
                var alt = pending[id];
                apiFetch({ path: '/wp/v2/media/' + id, method: 'POST', data: { alt_text: alt } }).then(function () { if (pending[id] === alt) delete pending[id]; }, function () {});
            });
        }
        wasSaving = saving;
    });

    function setAlt(block, alt) {
        data.dispatch(BLOCKS).updateBlockAttributes(block.clientId, { alt: alt });
        pending[block.attributes.id] = alt;
    }

    /* ---------- small pieces ---------- */
    function useBusy() {
        var s = useState({ busy: false, msg: '', err: false });
        return [s[0], function (busy, msg, err) { s[1]({ busy: busy, msg: msg || '', err: !!err }); }];
    }
    function Status(p) {
        if (!p.state.busy && !p.state.msg) return null;
        return el('p', { style: { margin: '6px 0 0', color: p.state.err ? '#b32d2e' : '#50575e', fontSize: '12px' } },
            p.state.busy ? el(C.Spinner, null) : null, ' ', p.state.msg);
    }
    function needKey() { return el('p', { style: { color: '#646970' } }, 'Needs the API key in wp-config.php (Artworks > AI writing).'); }
    function Chip(p) {
        return el(C.Button, { variant: 'secondary', size: 'small', style: { margin: '0 6px 6px 0' }, onClick: p.onClick, disabled: p.disabled }, p.label);
    }
    function useImages() {
        return wp.data.useSelect(function (select) { return flatten(select(BLOCKS).getBlocks()).filter(function (b) { return b.attributes.id; }); }, []);
    }

    /* ---------- alt text ---------- */
    function writeOne(block) {
        return call('ms_ai_post_alt', { image_id: block.attributes.id }).then(function (r) { setAlt(block, r.alt); return r; });
    }
    function writeMissing(setS) {
        var todo = flatten(data.select(BLOCKS).getBlocks()).filter(function (b) { return b.attributes.id && !(b.attributes.alt || '').trim(); });
        if (!todo.length) { setS(false, 'Every picture has alt text.'); return; }
        var done = 0;
        (function next() {
            if (!todo.length) { setS(false, 'Wrote ' + done + '. Check them below, then save the post.'); return; }
            setS(true, 'Writing ' + (done + 1) + ' of ' + (done + todo.length) + '...');
            writeOne(todo.shift()).then(function () { done++; next(); }, function (e) { setS(false, e.message + (done ? ' (' + done + ' written.)' : ''), true); });
        })();
    }
    function AltTool(p) {
        var s = useBusy(), imgs = useImages();
        var missing = imgs.filter(function (b) { return !(b.attributes.alt || '').trim(); }).length;
        if (!imgs.length) return el('p', { style: { color: '#646970' } }, 'No pictures from the Media Library in this post.');
        return el(Fragment, null,
            cfg.ai ? el(C.Button, { variant: 'secondary', disabled: s[0].busy || !missing, onClick: function () { writeMissing(s[1]); } },
                missing ? 'Write alt text for ' + missing + ' picture' + (missing > 1 ? 's' : '') : 'Every picture has alt text') : needKey(),
            el(Status, { state: s[0] }),
            p.list === false ? null : imgs.map(function (b) {
                return el('div', { key: b.clientId, style: { display: 'flex', gap: '8px', margin: '10px 0', alignItems: 'flex-start' } },
                    el('img', { src: b.attributes.url, alt: '', style: { width: '56px', height: '56px', objectFit: 'cover', borderRadius: '3px', flex: '0 0 auto' } }),
                    el('div', { style: { flex: '1 1 auto' } },
                        el(C.TextareaControl, { label: 'Alt text', hideLabelFromVision: true, rows: 3, value: b.attributes.alt || '', onChange: function (v) { setAlt(b, v); } }),
                        cfg.ai ? el(C.Button, { variant: 'link', onClick: function () {
                            if ((b.attributes.alt || '').trim() && !window.confirm('Write it again? This costs a little more API credit (about a cent).')) return;
                            s[1](true, 'Writing...'); writeOne(b).then(function () { s[1](false, 'Written - check it, then save the post.'); }, function (e) { s[1](false, e.message, true); });
                        } }, (b.attributes.alt || '').trim() ? 'Write again' : 'Write') : null));
            }));
    }

    /* ---------- tags ---------- */
    function addTag(id) {
        var cur = attr('tags') || [];
        if (cur.indexOf(id) < 0) data.dispatch(STORE).editPost({ tags: cur.concat([id]) });
    }
    function TagTool() {
        var s = useBusy(), r = useState(null), res = r[0];
        var tagCount = (data.useSelect ? data.useSelect(function (select) { return (select(STORE).getEditedPostAttribute('tags') || []).length; }, []) : 0);
        if (!cfg.ai) return needKey();
        return el(Fragment, null,
            el(C.Button, { variant: 'secondary', disabled: s[0].busy, onClick: function () {
                if (res && !window.confirm('Ask again? This costs a little more API credit (about a cent).')) return;
                s[1](true, 'Looking...');
                call('ms_ai_post_tags').then(function (d) { r[1](d); s[1](false, (d.tags.length + d.new.length) ? 'Pick the ones you want.' : 'Nothing to add.'); }, function (e) { s[1](false, e.message, true); });
            } }, res ? 'Suggest tags again' : 'Suggest tags'),
            el(Status, { state: s[0] }),
            res ? el('div', { style: { marginTop: '8px' } },
                res.tags.map(function (t) { return el(Chip, { key: 'e' + t.id, label: '+ ' + t.name, onClick: function () { addTag(t.id); } }); }),
                res.new.map(function (n) { return el(Chip, { key: 'n' + n, label: '+ ' + n + ' (new)', onClick: function () {
                    apiFetch({ path: '/wp/v2/tags', method: 'POST', data: { name: n } }).then(function (t) { addTag(t.id); }, function (e) { s[1](false, (e && e.message) || 'Could not add the tag.', true); });
                } }); })) : null,
            res && (res.tags.length + res.new.length) ? el(C.Button, { variant: 'link', onClick: function () { res.tags.forEach(function (t) { addTag(t.id); }); } }, 'Add all existing') : null,
            el('p', { style: { margin: '6px 0 0', color: '#646970', fontSize: '12px' } }, tagCount ? 'The post has ' + tagCount + ' tag' + (tagCount > 1 ? 's' : '') + '.' : 'The post has no tags yet.'));
    }

    /* ---------- category ---------- */
    function CategoryTool() {
        var s = useBusy(), r = useState(null), res = r[0];
        if (!cfg.ai) return needKey();
        return el(Fragment, null,
            el(C.Button, { variant: 'secondary', disabled: s[0].busy, onClick: function () {
                s[1](true, 'Looking...');
                call('ms_ai_post_category').then(function (d) { r[1](d); s[1](false, d.categories.length ? '' : 'None of the categories fits.'); }, function (e) { s[1](false, e.message, true); });
            } }, 'Suggest category'),
            el(Status, { state: s[0] }),
            res ? el('div', { style: { marginTop: '8px' } }, res.categories.map(function (c) {
                return el(Chip, { key: c.id, label: 'Use: ' + c.name, onClick: function () {
                    var cur = (attr('categories') || []).filter(function (x) { return x !== 1; });
                    if (cur.indexOf(c.id) < 0) cur.push(c.id);
                    data.dispatch(STORE).editPost({ categories: cur });
                } });
            })) : null);
    }

    /* ---------- excerpt ---------- */
    function ExcerptTool() {
        var s = useBusy(), r = useState(''), text = r[0];
        var has = data.useSelect(function (select) { return (select(STORE).getEditedPostAttribute('excerpt') || '').trim() !== ''; }, []);
        if (!cfg.ai) return needKey();
        return el(Fragment, null,
            el('p', { style: { margin: '0 0 8px', color: '#646970', fontSize: '12px' } }, has ? 'The post has an excerpt (it shows under the picture in the feed and in search results).' : 'No excerpt yet: the feed shows the first words of the text.'),
            el(C.Button, { variant: 'secondary', disabled: s[0].busy, onClick: function () {
                if (text && !window.confirm('Ask again? This costs a little more API credit (about a cent).')) return;
                s[1](true, 'Writing...');
                call('ms_ai_post_excerpt').then(function (d) { r[1](d.excerpt); s[1](false, 'Edit it if you like, then use it.'); }, function (e) { s[1](false, e.message, true); });
            } }, text ? 'Suggest again' : 'Suggest excerpt'),
            el(Status, { state: s[0] }),
            text ? el('div', { style: { marginTop: '8px' } },
                el(C.TextareaControl, { label: 'Excerpt', hideLabelFromVision: true, rows: 3, value: text, onChange: r[1] }),
                el('p', { style: { margin: '0 0 6px', color: '#646970', fontSize: '12px' } }, text.length + ' characters'),
                el(C.Button, { variant: 'primary', onClick: function () { data.dispatch(STORE).editPost({ excerpt: text }); s[1](false, 'Used as the excerpt - save the post.'); } }, 'Use as excerpt')) : null);
    }

    /* ---------- a caption to share ---------- */
    function ShareTool() {
        var s = useBusy(), r = useState(null), res = r[0];
        if (!cfg.ai) return needKey();
        return el(Fragment, null,
            el(C.Button, { variant: 'secondary', disabled: s[0].busy, onClick: function () {
                if (res && !window.confirm('Ask again? This costs a little more API credit (about a cent).')) return;
                s[1](true, 'Writing...');
                call('ms_ai_post_share').then(function (d) { r[1](d); s[1](false, d.has_link ? '' : 'Save the post first and its address is filled in.'); }, function (e) { s[1](false, e.message, true); });
            } }, res ? 'Draft again' : 'Draft a caption to share'),
            el(Status, { state: s[0] }),
            res ? el('div', { style: { marginTop: '8px' } },
                el(C.TextareaControl, { label: 'Caption', hideLabelFromVision: true, rows: 5, value: res.caption, onChange: function (v) { r[1]({ caption: v, has_link: res.has_link }); } }),
                el(C.Button, { variant: 'secondary', onClick: function () {
                    var done = function () { s[1](false, 'Copied.'); };
                    if (navigator.clipboard) navigator.clipboard.writeText(res.caption).then(done, function () { s[1](false, 'Select the text and copy it.', true); });
                    else s[1](false, 'Select the text and copy it.', true);
                } }, 'Copy')) : null);
    }

    /* ---------- an artwork card ---------- */
    function ArtworkTool() {
        var s = useBusy(), q = useState(''), r = useState([]);
        function search() {
            if (!q[0].trim()) return;
            s[1](true, 'Searching...');
            call('ms_post_find_artwork', { q: q[0].trim() }).then(function (d) { r[1](d.items); s[1](false, d.items.length ? '' : 'No artwork found.'); }, function (e) { s[1](false, e.message, true); });
        }
        function insert(n) {
            var block = wp.blocks.createBlock('core/shortcode', { text: '[artwork sku="' + n + '" info="yes" availability="yes"]' });
            data.dispatch(BLOCKS).insertBlocks(block);
            s[1](false, 'Inserted the card for #' + n + '.');
        }
        return el(Fragment, null,
            el('div', { style: { display: 'flex', gap: '6px', alignItems: 'flex-end' } },
                el('div', { style: { flex: '1 1 auto' } }, el(C.TextControl, { label: 'Artwork title or number', value: q[0], onChange: q[1], onKeyDown: function (e) { if (e.key === 'Enter') { e.preventDefault(); search(); } } })),
                el(C.Button, { variant: 'secondary', onClick: search, style: { marginBottom: '8px' } }, 'Find')),
            el(Status, { state: s[0] }),
            r[0].map(function (it) {
                return el('div', { key: it.number, style: { display: 'flex', gap: '8px', alignItems: 'center', margin: '8px 0' } },
                    it.thumb ? el('img', { src: it.thumb, alt: '', style: { width: '40px', height: '40px', objectFit: 'cover', borderRadius: '3px' } }) : null,
                    el('div', { style: { flex: '1 1 auto', fontSize: '13px' } }, '#' + it.number + ' ' + it.title),
                    el(C.Button, { variant: 'secondary', size: 'small', onClick: function () { insert(it.number); } }, 'Insert'));
            }));
    }

    /* ---------- the sidebar panel ---------- */
    function Section(p) {
        return el('div', { style: { margin: '0 0 18px' } }, el('p', { style: { margin: '0 0 6px', fontWeight: 600 } }, p.title), p.children);
    }
    function WritingHelp() {
        return el(Panel, { name: 'ms-post-tools', title: 'Writing help', className: 'ms-post-tools', initialOpen: false },
            el(Section, { title: 'Alt text for the pictures' }, el(AltTool)),
            el(Section, { title: 'Tags' }, el(TagTool)),
            el(Section, { title: 'Category' }, el(CategoryTool)),
            el(Section, { title: 'Excerpt' }, el(ExcerptTool)),
            el(Section, { title: 'Caption to share' }, el(ShareTool)),
            el(Section, { title: 'Artwork card' }, el(ArtworkTool)));
    }

    /* ---------- before you publish ---------- */
    function BeforePublish() {
        if (!PrePublish) return null;
        var st = data.useSelect(function (select) {
            var s = select(STORE), imgs = flatten(select(BLOCKS).getBlocks()).filter(function (b) { return b.attributes.id; });
            return {
                noAlt: imgs.filter(function (b) { return !(b.attributes.alt || '').trim(); }).length,
                tags: (s.getEditedPostAttribute('tags') || []).length,
                excerpt: (s.getEditedPostAttribute('excerpt') || '').trim() !== '',
                featured: !!s.getEditedPostAttribute('featured_media')
            };
        }, []);
        var allGood = !st.noAlt && st.tags && st.excerpt && st.featured;
        return el(PrePublish, { title: 'Before you publish', initialOpen: !allGood },
            allGood ? el('p', null, 'Pictures have alt text, the post has tags and an excerpt.') : null,
            st.featured ? null : el('p', { style: { color: '#b32d2e' } }, 'No featured image: the feed card will use the first picture or a placeholder.'),
            st.noAlt ? el(Section, { title: st.noAlt + ' picture' + (st.noAlt > 1 ? 's' : '') + ' without alt text' }, el(AltTool, { list: false })) : null,
            st.tags ? null : el(Section, { title: 'No tags yet' }, el(TagTool)),
            st.excerpt ? null : el(Section, { title: 'No excerpt' }, el(ExcerptTool)));
    }

    wp.plugins.registerPlugin('ms-post-tools', { render: function () { return el(Fragment, null, el(WritingHelp), el(BeforePublish)); } });
})(window.wp, window.msPostTools);
