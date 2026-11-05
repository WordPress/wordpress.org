/**
 * Video editor: one VideoPress block, legacy shortcode conversion and a single
 * "Video details" panel. Uses only WordPress-provided packages; no build step.
 * Server-side rules live in inc/editor.php.
 */
((wp) => {
	'use strict';

	const { blocks, blockEditor, components, coreData, data, domReady, editor, element, hooks, htmlEntities, i18n, plugins } = wp;

	const el = element.createElement;
	const { __ } = i18n;
	const VIDEO = 'videopress/video';
	const SHORTCODE = /^\s*\[wpvideo\s+([A-Za-z0-9]{8})(?:\s[^\]]*)?\]\s*$/;

	// Player alignment and layout are global; moderators only replace the video
	// and manage its poster (featured image) and subtitles.
	hooks.addFilter('blocks.registerBlockType', 'wptv/video-supports', (settings, name) => {
		if (name !== VIDEO) {
			return settings;
		}
		return { ...settings, supports: { ...settings.supports, align: false, anchor: false, spacing: false, customClassName: false, className: false, html: false } };
	});

	// Hide the block settings sidebar while the video block is selected, and the
	// toolbar controls replaced by global rules: caption (none), poster (the
	// featured image) and chapters. Labels are translated at runtime so the rules
	// follow the user's admin language. Replace and subtitles stay.
	const hiddenLabels = [
		__('Add caption', 'jetpack-videopress-pkg'),
		__('Add caption'),
		__('Poster image', 'jetpack-videopress-pkg'),
		__('Manage chapters', 'jetpack-videopress-pkg'),
	];
	const toolbar = '.block-editor-block-toolbar';
	// Hide summary rows, not their saved values or the underlying permissions.
	const authorLabel = __('Change author: %s').split('%s');
	const authorMatch = authorLabel.map((part, index) => part ? `[aria-label${index ? '$' : '^'}="${CSS.escape(part)}"]` : '').join('');
	const hiddenSummaryRows = [
		`.editor-post-panel__row:has(button${authorMatch})`,
		`.editor-post-panel__row:has(button[aria-label="${CSS.escape(__('Template options'))}"])`,
	];
	const style = document.createElement('style');
	style.textContent = '[role="tab"][data-tab-id="edit-post/block"] { display: none; }\n' +
		hiddenSummaryRows.join(',\n') + ' { display: none; }\n' +
		// A locked single-video canvas needs neither insertion nor an outline.
		'body.post-type-post .editor-header .editor-document-tools__inserter-toggle, body.post-type-post .editor-header .editor-document-tools__document-overview-toggle { display: none; }\n' +
		'body.wptv-video-selected .block-editor-block-inspector { display: none; }\n' +
		// Published videos cannot use this selector; retain it for other statuses.
		'.edit-flow-extended-post-status-publish { display: none; }\n' +
		[...new Set(hiddenLabels)].map((label) => `body.wptv-video-selected ${toolbar} button[aria-label="${CSS.escape(label)}"]`).join(',\n') +
		' { display: none; }\n' +
		// Native-admin review table: label, submitted value, approval action.
		'.wptv-review { width: 100%; max-width: 960px; margin: 0 auto; border-collapse: collapse; table-layout: fixed; }\n' +
		'.wptv-review th { width: 160px; text-align: left; font-weight: 600; }\n' +
		'.wptv-review th, .wptv-review td { padding: 8px 10px; vertical-align: top; }\n' +
		'.wptv-review__action { width: 90px; }\n' +
		'.wptv-review .components-base-control { width: 100%; margin: 0; }\n' +
		'.wptv-review .components-base-control__field { margin: 0; }\n' +
		'.wptv-review__links { display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 16px; overflow-wrap: anywhere; }\n' +
		'@media (max-width: 600px) { .wptv-review th { width: 100px; } .wptv-review th, .wptv-review td { padding: 8px 4px; } .wptv-review__action { width: 80px; } }';
	document.head.appendChild(style);

	/** Converts a post whose whole content is one legacy shortcode. */
	function convertLegacyShortcode() {
		const current = data.select(blockEditor.store).getBlocks();
		if (current.length !== 1 || current[0].name === VIDEO) {
			return;
		}
		const raw = current[0].attributes.content ?? current[0].attributes.text ?? '';
		const text = String(raw?.toString?.() ?? raw).replace(/<[^>]*>/g, '');
		const match = text.match(SHORTCODE);
		if (!match) {
			if (/\[wpvideo/i.test(text)) {
				data.dispatch('core/notices').createWarningNotice(
					__('This video still uses a legacy shortcode together with other content, so it was not converted automatically.', 'wptv')
				);
			}
			return;
		}
		// A regular post edit, so the editor offers to save the converted content.
		const video = [blocks.createBlock(VIDEO, { guid: match[1] })];
		data.dispatch(editor.store).editPost({ blocks: video, content: blocks.serialize(video) });
		data.dispatch(blockEditor.store).setTemplateValidity(true);
		data.dispatch('core/notices').createInfoNotice(
			__('The legacy video shortcode was converted to a VideoPress block. Save the post to keep the change.', 'wptv'),
			{ type: 'snackbar' }
		);
	}

	/** Returns the ID of a term by name, creating it when missing. */
	async function findOrCreateTerm(taxonomy, label) {
		try {
			return (await data.dispatch(coreData.store).saveEntityRecord('taxonomy', taxonomy, { name: label }, { throwOnError: true })).id;
		} catch (error) {
			if (error?.code === 'term_exists' && error.data?.term_id) {
				return error.data.term_id;
			}
			throw error;
		}
	}

	/** Searchable token field for one native taxonomy. */
	function TermField({ taxonomy, restBase, label, max }) {
		const [ids, setIds] = coreData.useEntityProp('postType', 'post', restBase);
		const [search, setSearch] = element.useState('');
		const selectedIds = Array.isArray(ids) ? ids : [];
		const { selected, suggestions } = data.useSelect((select) => {
			const store = select(coreData.store);
			return {
				selected: selectedIds.length
					? store.getEntityRecords('taxonomy', taxonomy, { include: selectedIds, per_page: 100, context: 'view' }) || []
					: [],
				suggestions: search.length > 1
					? store.getEntityRecords('taxonomy', taxonomy, { search, per_page: 20, orderby: 'count', order: 'desc', context: 'view' }) || []
					: [],
			};
		}, [taxonomy, selectedIds.join(','), search]);
		const name = (term) => htmlEntities.decodeEntities(term.name);
		const known = [...selected, ...suggestions];
		const value = selectedIds.map((id) => known.find((term) => term.id === id)).filter(Boolean).map(name);
		// Never edit before assigned terms load, or a change could drop them.
		const loading = value.length !== selectedIds.length;

		async function onChange(tokens) {
			const next = [];
			for (const token of tokens.slice(0, max || tokens.length)) {
				const label = typeof token === 'string' ? token.trim() : token.value;
				const existing = known.find((term) => name(term).toLowerCase() === label.toLowerCase());
				if (existing) {
					next.push(existing.id);
				} else if (label) {
					next.push(await findOrCreateTerm(taxonomy, label));
				}
			}
			setIds([...new Set(next)]);
		}

		return el(components.FormTokenField, {
			label,
			help: '',
			value,
			suggestions: suggestions.map(name),
			onInputChange: setSearch,
			onChange: (tokens) => onChange(tokens).catch((error) => data.dispatch('core/notices').createErrorNotice(error.message)),
			maxLength: max,
			disabled: loading,
			__experimentalExpandOnFocus: true,
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
		});
	}

	/**
	 * Review panel for anonymous submissions (wptv2 "Submitted video" box).
	 * Each value is editable; Approve copies it to the real field. Terms are
	 * created only on approval, so anonymous input never reaches taxonomies alone.
	 */
	function SubmissionReview({ submission }) {
		const [values, setValues] = element.useState(submission);
		const [approved, setApproved] = element.useState({});
		const [defaultRemoved, setDefaultRemoved] = element.useState(false);
		const { editPost } = data.useDispatch(editor.store);
		const post = (key) => data.select(editor.store).getEditedPostAttribute(key);
		const done = (key) => setApproved((current) => ({ ...current, [key]: true }));
		const fail = (error) => data.dispatch('core/notices').createErrorNotice(error.message || String(error));

		async function approveTerms(key, taxonomy, restBase, single) {
			const labels = String(values[key]).split(',').map((label) => label.trim()).filter(Boolean);
			const ids = [];
			for (const label of single ? labels.slice(0, 1) : labels) {
				ids.push(await findOrCreateTerm(taxonomy, label));
			}
			editPost({ [restBase]: [...new Set(single ? ids : [...(post(restBase) || []), ...ids])] });
			done(key);
		}
		function approveCategory(key, id) {
			let categories = post('categories') || [];
			// As in wptv2, the first approved category removes the WordCampTV default once.
			if (!defaultRemoved) {
				categories = categories.filter((category) => category !== submission.defaultCategory);
				setDefaultRemoved(true);
			}
			editPost({ categories: [...new Set([...categories, id])] });
			done(key);
		}

		const button = (key, onClick) => el(components.Button, {
			variant: 'secondary',
			size: 'compact',
			disabled: !!approved[key] || (typeof values[key] === 'string' && !values[key].trim()),
			onClick: () => Promise.resolve(onClick()).catch(fail),
		}, approved[key] ? __('Approved', 'wptv') : __('Approve', 'wptv'));
		const row = (label, content, action, key) => el('tr', { className: 'wptv-review__field', key },
			el('th', { scope: 'row' }, label),
			el('td', null, content),
			el('td', { className: 'wptv-review__action' }, action)
		);
		const field = (key, label, onApprove, multiline) => row(
			el('label', { htmlFor: 'wptv-review-' + key }, label),
			el(multiline ? components.TextareaControl : components.TextControl, {
				id: 'wptv-review-' + key,
				value: values[key] || '',
				rows: multiline ? 6 : undefined,
				onChange: (value) => setValues((current) => ({ ...current, [key]: value })),
				__next40pxDefaultSize: !multiline || undefined,
				__nextHasNoMarginBottom: true,
			}),
			button(key, onApprove),
			key
		);
		const categoryButton = (category, key) => el(components.Button, {
			key,
			variant: 'secondary',
			size: 'compact',
			disabled: !!approved[key],
			onClick: () => approveCategory(key, category.id),
		}, category.name);

		return el('table', { className: 'wptv-review', 'aria-label': __('Submitted video', 'wptv') }, el('tbody', null,
				row(__('Submitted by', 'wptv'), el('div', { className: 'wptv-review__links' },
					el('span', null, submission.submitted_by),
					el('a', { href: `mailto:${submission.submitted_email}?Subject=Your%20WordPress.tv%20submission` }, submission.submitted_email),
					el(components.ExternalLink, { href: `http://en.utrace.de/?query=${encodeURIComponent(submission.ip)}` }, submission.ip)
				)),
				field('title', __('Title', 'wptv'), () => { editPost({ title: values.title }); done('title'); }),
				field('language', __('Language', 'wptv'), () => approveTerms('language', 'language', 'language')),
				submission.recorded && row(__('Date recorded', 'wptv'), submission.recorded,
					submission.yearCategory && categoryButton(submission.yearCategory, 'year')),
				submission.categories.length > 0 && row(__('Categories', 'wptv'),
					el('div', { className: 'wptv-review__links' }, submission.categories.map((category) => categoryButton(category, 'category-' + category.id)))),
				field('event', __('Event', 'wptv'), () => approveTerms('event', 'event', 'event')),
				field('producer_username', __('Producer WordPress.org username', 'wptv'), () => approveTerms('producer_username', 'producer-username', 'producer-username', true)),
				field('speakers', __('Speakers', 'wptv'), () => approveTerms('speakers', 'speakers', 'speakers')),
				field('description', __('Description', 'wptv'), () => { editPost({ excerpt: values.description }); done('description'); }, true),
				field('slides', __('Slides', 'wptv'), () => { editPost({ meta: { ...(post('meta') || {}), _wptv_slides_url: values.slides } }); done('slides'); }),
				submission.attachment && row(__('Uploaded file', 'wptv'), el('div', { className: 'wptv-review__links' },
					el(components.ExternalLink, { href: submission.attachment.url }, submission.attachment.title),
					el(components.ExternalLink, { href: submission.attachment.edit }, __('Edit attachment', 'wptv'))
				))
		));
	}
	/** Renders the review panel into the "Submitted video" meta box below the content. */
	function SubmissionBox() {
		const [root, setRoot] = element.useState(() => document.getElementById('wptv-submission-root'));
		element.useEffect(() => {
			if (root) {
				return undefined;
			}
			// Meta boxes mount after the editor; wait for the box.
			const observer = new MutationObserver(() => {
				const found = document.getElementById('wptv-submission-root');
				if (found) {
					observer.disconnect();
					setRoot(found);
				}
			});
			observer.observe(document.body, { childList: true, subtree: true });
			return () => observer.disconnect();
		}, [root]);
		return root ? element.createPortal(el(SubmissionReview, { submission: window.wptvSubmission }), root) : null;
	}
	if (window.wptvSubmission) {
		plugins.registerPlugin('wptv-submission', { render: SubmissionBox });
	}

	function VideoDetails() {
		const excerpt = data.useSelect((select) => select(editor.store).getEditedPostAttribute('excerpt'), []);
		const [meta, setMeta] = coreData.useEntityProp('postType', 'post', 'meta');
		const { editPost } = data.useDispatch(editor.store);
		return el(editor.PluginDocumentSettingPanel, { name: 'wptv-video-details', title: __('Video details', 'wptv'), initialOpen: true },
			el(components.__experimentalVStack, { spacing: 4 },
				el(TermField, { taxonomy: 'language', restBase: 'language', label: __('Language', 'wptv') }),
				el(TermField, { taxonomy: 'category', restBase: 'categories', label: __('Categories', 'wptv') }),
				el(TermField, { taxonomy: 'post_tag', restBase: 'tags', label: __('Tags', 'wptv') }),
				el(TermField, { taxonomy: 'event', restBase: 'event', label: __('Event', 'wptv') }),
				el(TermField, { taxonomy: 'producer-username', restBase: 'producer-username', label: __('Producer WordPress.org username', 'wptv'), max: 1 }),
				el(TermField, { taxonomy: 'speakers', restBase: 'speakers', label: __('Speakers', 'wptv') }),
				el(components.TextareaControl, {
					label: __('Description (excerpt)', 'wptv'),
					value: excerpt || '',
					rows: 10,
					onChange: (value) => editPost({ excerpt: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(components.TextControl, {
					label: __('Slides URL', 'wptv'),
					type: 'url',
					value: meta?._wptv_slides_url || '',
					onChange: (value) => setMeta({ ...meta, _wptv_slides_url: value }),
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
				})
			)
		);
	}
	plugins.registerPlugin('wptv-video-details', { render: VideoDetails });

	domReady(() => {
		['speakers', 'event', 'language', 'producer-username', 'category', 'post_tag'].forEach((taxonomy) => {
			data.dispatch(editor.store).removeEditorPanel('taxonomy-panel-' + taxonomy);
		});
		data.dispatch(editor.store).removeEditorPanel('post-excerpt');
		// Panel state lives in user preferences; the video data always starts open.
		const panel = 'wptv-video-details/wptv-video-details';
		if (!data.select(editor.store).isEditorPanelOpened(panel)) {
			data.dispatch(editor.store).toggleEditorPanelOpened(panel);
		}

		// Redirect only the block sidebar. A closed sidebar must stay closed.
		function keepPostSidebar() {
			if (data.select('core/edit-post').getActiveGeneralSidebarName() === 'edit-post/block') {
				data.dispatch('core/edit-post').openGeneralSidebar('edit-post/document');
			}
		}
		keepPostSidebar();
		let converted = false;
		data.subscribe(() => {
			keepPostSidebar();
			const selected = data.select(blockEditor.store).getSelectedBlock();
			document.body.classList.toggle('wptv-video-selected', selected?.name === VIDEO);
			// The editor can report ready before the post's blocks are in the store.
			if (!converted && data.select(editor.store).__unstableIsEditorReady() && data.select(blockEditor.store).getBlockCount() > 0) {
				converted = true;
				convertLegacyShortcode();
			}
		});
	});
})(window.wp);
