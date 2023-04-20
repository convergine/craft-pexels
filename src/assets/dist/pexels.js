(function () {

	var insertContent = function () {

		if (typeof Craft == 'object' && Craft !== null && typeof Craft.createElementSelectorModal == 'function') {
			/* craft JS has finished loading */
			clearInterval(interval);
			Craft.createElementSelectorModal = (function (selectorModalFn) {
				return function (type, config) {
					var elementModal = selectorModalFn.call(this, type, config);

					if (type == 'craft\\elements\\Asset') {

						/* create Pexels button */
						var $btn = $(pxButton);
						var pexelsModal;
						var selectedImages = [];
						var isDownloading = false;
						var uploadedAssets = [];

						/* on button click */
						$btn.click(function (e) {

							if (!pexelsModal) {
								/* create modal */
								var $pexelsModal = $(pxModal);
								var requestId;

								pexelsModal = new Garnish.Modal($pexelsModal, {});
								pexelsModal.on('fadeIn', function (e) {
									$pexelsModal.find('.pexels-search').focus();
								});

								var pexelsSearch = function (query, page, orientation, size, color) {
									/* search query processing */

									if (typeof query != 'string' || query.trim() == '') {
										$pexelsModal.find('.pexels-results').html(Craft.escapeHtml(pxHolderText));
										return;
									}

									page = page || 1;
									orientation = orientation || '';
									size = size || '';
									color = color || '';

									var currentReq = Math.random().toString().replace('0.', '');
									requestId = currentReq;

									$pexelsModal.find('.pexels-results').html(pxLoading);

									$.ajax(Craft.getActionUrl('convergine-pexels/pexels/search-pexels'), {
										data: { query: query, page: page, orientation: orientation, size: size, color: color },
										dataType: 'json',
										success: function (result) {

											if (requestId != currentReq) {
												return;
											}

											if (!result || !result.success) {
												/* error? */
												$pexelsModal.find('.pexels-results').html(`<p class="error">${Craft.escapeHtml(Craft.t('convergine-pexels', 'An error occurred while loading Pexels data'))}</p>`);
												console.error(result);
											}

											if (!result.data || !result.data.photos || !result.data.photos.length) {
												/* no results returned */
												$pexelsModal.find('.pexels-results').html(Craft.escapeHtml(Craft.t('convergine-pexels', '0 photos found for "{query}"', { query: result.data.query })));
												return;
											}

											let html = '';

											const hasNext = (result.data.photos.length + (result.data.page - 1) * pxPerPage < result.data.total_results);
											const hasPrev = (result.data.page > 1);
											const lastPage = Math.ceil(result.data.total_results / pxPerPage);

											/* prepare pagination */
											html += `<div class="d-flex justify-content-between align-items-center mb-2 pexels-pagination">
												<div class="p-2"><p class="pexels-pagelabel mb-0">${Craft.escapeHtml(Craft.t('convergine-pexels', 'Page {p} of {lp}', { p: result.data.page, lp: lastPage }))}</p></div>
												<div class="p-2">
													<button class="btn pexels-page-button ${hasPrev ? '' : ' disabled'}" data-query="${Craft.escapeHtml(result.data.query)}" data-page="${Craft.escapeHtml(result.data.page - 1)}" data-orientation="${Craft.escapeHtml(result.data.orientation)}" data-size="${Craft.escapeHtml(result.data.size)}" data-color="${Craft.escapeHtml(result.data.color)}">${Craft.escapeHtml(Craft.t('convergine-pexels', 'Previous {picPerPage}', { picPerPage: pxPerPage }))}</button>
													<button class="btn secondary pexels-page-button ${hasNext ? '' : ' disabled'}" data-query="${Craft.escapeHtml(result.data.query)}" data-page="${Craft.escapeHtml(result.data.page + 1)}" data-orientation="${Craft.escapeHtml(result.data.orientation)}" data-size="${Craft.escapeHtml(result.data.size)}" data-color="${Craft.escapeHtml(result.data.color)}">${Craft.escapeHtml(Craft.t('convergine-pexels', 'Next {picPerPage}', { picPerPage: pxPerPage }))}</button>
												</div>
											</div>`;

											html += '<div class="flex">';

											/* prepare photo results */
											html += result.data.photos.map(pic => {
												const url = pic.src['original'];
												const isSelected = selectedImages.indexOf(url) > -1 ? ' selected' : '';
												return `<label class="pexels-photo${isSelected}" tabindex="0" style="background-image: url(${Craft.escapeHtml(pic.src['tiny'])});" data-image="${Craft.escapeHtml(url)}" title="${Craft.escapeHtml(pic.alt)}"></label>`;
											}).join('');

											html += '<div></div>'.repeat(pxPerPage - result.data.photos.length);

											$pexelsModal.find('.pexels-results').html(html);

											const selectItem = function () {
												const $this = $(this);
												const imageData = $this.data('image');
												const key = selectedImages.indexOf(imageData);
												const $pexelsDownload = $pexelsModal.find('.pexels-download');
												const $pexelsSelected = $pexelsModal.find('.pexels-selected');

												if (key > -1) {
													selectedImages.splice(key, 1);
													$this.removeClass('selected');
												} else {
													$this.addClass('selected');
													selectedImages.push(imageData);
												}

												const hasSelectedImages = selectedImages.length > 0;
												$pexelsDownload.toggleClass('disabled', !hasSelectedImages);
												$pexelsSelected.html(hasSelectedImages ? Craft.escapeHtml(Craft.t('convergine-pexels', '{n,plural,=1{# picture} other{# pictures}} selected', { n: selectedImages.length })) : '');
											};

											$pexelsModal.find('.pexels-photo').click(selectItem);

											$pexelsModal.find('.pexels-page-button').click(function () {
												/* pagination button handler */

												const $this = $(this);
												const page = $this.data('page');
												const query = $this.data('query');
												const orientation = $this.data('orientation');
												const size = $this.data('size');
												const color = $this.data('color');

												if (!page || page < 1 || page > lastPage) {
													return;
												}

												pexelsSearch(query, page, orientation, size, color);
											});

											$pexelsModal.find('.pexels-download').click(function () {
												/* download button handler */

												if (isDownloading || !selectedImages.length)
													return;

												$(this).addClass('disabled');
												$pexelsModal.find('.body').addClass('pexels-downloader-body');
												$pexelsModal.find('.body').html(`<div class="pexels-downloader progress-shade"><p><b>${Craft.escapeHtml(Craft.t('convergine-pexels', 'Downloading, please wait.'))}</b></p><div class="progressbar"><div class="progressbar-inner" style="width: 0%;"></div></div><div class="progressbar-status"></div></div>`);

												isDownloading = true;
												uploadedAssets = [];

												pexelsDownload(selectedImages);

											});
										},
										error: function (xhr) {

											console.error(xhr);
											$pexelsModal.find('.pexels-results').html(`<p class="error">${Craft.escapeHtml(xhr.status + ' ' + xhr.statusText)}</p>`);

										}
									});
								};

								var pexelsDownload = function (urls, n) {
									/* download array of Pexels URLs and save them to the server */

									if (!isDownloading) {
										return;
									}

									if (typeof n == 'undefined') {
										n = 0;
									}

									if (!urls || typeof urls != 'object' || typeof urls[n] == 'undefined') {
										return;
									}

									$.ajax(Craft.getActionUrl('convergine-pexels/pexels/download'), {
										data: { url: urls[n], target: elementModal.elementIndex.sourceKey },
										dataType: 'json',
										success: function (result) {

											if (typeof result != 'object' || !result) {

												console.error(result);

											} else {

												if (result.assetId){
													elementModal.elementIndex.selectElementAfterUpdate(result.assetId);
												}

												$pexelsModal.find('.pexels-downloader .progressbar-inner').css('width', ((n + 1) / urls.length * 100) + '%');

												if (typeof urls[n + 1] != 'undefined') {
													pexelsDownload(urls, n + 1);
													return;
												}
											}

											pexelsModal.hide();
											pexelsModal = null;
											isDownloading = false;
											elementModal.elementIndex.updateElements();

										},
										error: function (xhr) {

											console.error(xhr);

											$pexelsModal.find('.pexels-downloader').html('<p class="error">' + Craft.escapeHtml(xhr.status + ' ' + xhr.statusText) + '</p>');

											elementModal.elementIndex.updateElements();

										}
									});
								};

								/* search handler */
								var handleSearch = function () {
									selectedImages = [];
									$pexelsModal.find('.pexels-photo.selected').removeClass('selected');
									$pexelsModal.find('.pexels-selected').html('');
									pexelsSearch(
										$pexelsModal.find('.pexels-search').val(),
										null,
										$pexelsModal.find('.pexels-search-orientation').val(),
										$pexelsModal.find('.pexels-search-size').val(),
										$pexelsModal.find('.pexels-search-color').val()
									);
								}

								/* cancel button handler */
								var handleCancel = function () {
									selectedImages = [];
									$pexelsModal.find('.pexels-photo.selected').removeClass('selected');
									$pexelsModal.find('.pexels-selected').html('');
									pexelsModal.hide();
									if (isDownloading) {
										isDownloading = false;
										pexelsModal = null;
										elementModal.elementIndex.updateElements();
									}
								}

								/* listen for search form events */
								/* un-commenting the next line will send new API request upon changing any of the search parameters */
								/* $pexelsModal.find('.pexels-search, .pexels-search-orientation, .pexels-search-size, .pexels-search-color').change(handleSearch); */
								$pexelsModal.find('.pexels-search-button').click(handleSearch);
								$pexelsModal.find('.pexels-cancel').click(handleCancel);
								/* Listen for 'enter' on search field */
								$pexelsModal.find('.pexels-search').on('keydown', function (e) {
									if (e.which == 13)
										handleSearch();
								});

							} else {
								/* show previously created modal */
								pexelsModal.show();
							}

						});

						$btn.insertAfter(elementModal.$secondaryButtons);

					}
					return elementModal;
				};
			})(Craft.createElementSelectorModal);
		}
	};
	/* try until main javascript is ready */
	var interval = setInterval(insertContent, 10);
})();