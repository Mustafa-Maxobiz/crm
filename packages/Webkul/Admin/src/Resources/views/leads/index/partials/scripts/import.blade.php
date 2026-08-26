        @if (bouncer()->hasPermission(lead_permission('import')))
        <script type="module">
            const leadImportStartUrl = @json(lead_route('import.start'));
            const leadImportProcessUrl = @json(lead_route('import.process'));
            const leadImportRetryUrl = @json(lead_route('import.retry'));
            const leadImportMaxFileSize = 2 * 1024 * 1024;
            const failedEditFields = [
                'companies',
                'lead_value',
                'type',
                'pricing_type',
                'person_name',
                'email',
                'phone',
                'address',
                'city',
                'state',
                'country',
                'postcode',
            ];

            let importLeadSourceId = null;
            let importAssigneeUserIds = [];
            let importIndustryId = null;
            let importLinkedInProfileId = null;
            let importTagId = null;
            const isLgeLeadImport = @json(lead_variant() === 'lge');
            const isAdminLeadImport = @json(app(\Webkul\Lead\Services\SourceAccessService::class)->isAdmin());
            const coldLeadImportTagId = @json(app(\Webkul\Lead\Services\LeadForwardService::class)->coldLeadTagId());
            let failedImportRows = [];

            const importElements = () => ({
                form: document.getElementById('lead-import-form'),
                file: document.getElementById('lead-import-file'),
                submit: document.getElementById('lead-import-submit'),
                retry: document.getElementById('lead-import-retry'),
                progress: document.getElementById('lead-import-progress'),
                bar: document.getElementById('lead-import-progress-bar'),
                percent: document.getElementById('lead-import-progress-percent'),
                status: document.getElementById('lead-import-progress-status'),
                failed: document.getElementById('lead-import-failed'),
                failedBody: document.getElementById('lead-import-failed-body'),
                failedSummary: document.getElementById('lead-import-failed-summary'),
                failedTopScroll: document.getElementById('lead-import-failed-top-scroll'),
                failedTopSpacer: document.getElementById('lead-import-failed-top-scroll-spacer'),
                failedTableScroll: document.getElementById('lead-import-failed-table-scroll'),
                failedTable: document.getElementById('lead-import-failed-table'),
            });

            const setProgress = (elements, percent, status, isError = false) => {
                const boundedPercent = Math.max(0, Math.min(100, Number(percent)));
                const formattedPercent = boundedPercent.toFixed(2);

                elements.progress.classList.remove('hidden');
                elements.bar.style.width = `${boundedPercent}%`;
                elements.percent.textContent = `${formattedPercent}%`;
                elements.status.textContent = status;
                elements.bar.classList.toggle('bg-green-600', boundedPercent === 100 && ! isError);
                elements.bar.classList.toggle('bg-red-600', boundedPercent !== 100 || isError);
            };

            const flash = (type, message) => {
                window.emitter?.emit('add-flash', { type, message });
            };

            const isColdLeadImportSelected = () => {
                const tagSelect = document.getElementById('lead-import-tag');

                if (! tagSelect?.value) {
                    return false;
                }

                return Number(tagSelect.value) === Number(coldLeadImportTagId);
            };

            const syncLgeColdForwardPanel = () => {
                const panel = document.getElementById('lead-import-sdr-assignment-group');

                if (! panel || isAdminLeadImport) {
                    return;
                }

                panel.classList.toggle('hidden', ! isLgeLeadImport || ! isColdLeadImportSelected());
            };

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#39;');

            const fieldInput = (rowIndex, field, value) => `
                <td class="px-2 py-2 align-top">
                    <input
                        type="text"
                        data-row-index="${rowIndex}"
                        data-field="${field}"
                        value="${escapeHtml(value ?? '')}"
                        class="min-w-[120px] rounded border border-gray-200 px-2 py-1 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                    />
                </td>
            `;

            const syncFailedRowsFromTable = () => {
                failedImportRows = collectFailedRowsFromTable().map((row, index) => ({
                    ...row,
                    error: failedImportRows[index]?.error || 'Import failed',
                }));
            };

            const removeFailedRow = (index) => {
                syncFailedRowsFromTable();
                failedImportRows.splice(index, 1);
                renderFailedRows(failedImportRows, importLeadSourceId);

                if (! failedImportRows.length) {
                    setLeadImportFooterMode('done');
                    flash('success', 'All failed rows removed from correction list.');
                }
            };

            const removeAllFailedRows = () => {
                if (! failedImportRows.length) {
                    return;
                }

                if (! confirm(`Remove all ${failedImportRows.length} failed row(s) from correction?`)) {
                    return;
                }

                renderFailedRows([], importLeadSourceId);
                setLeadImportFooterMode('done');
                flash('success', 'Failed rows removed from correction list.');
            };

            const syncFailedTableScrollbars = () => {
                const elements = importElements();
                const topScroll = elements.failedTopScroll;
                const topSpacer = elements.failedTopSpacer;
                const tableScroll = elements.failedTableScroll;
                const table = elements.failedTable;

                if (! topScroll || ! topSpacer || ! tableScroll || ! table) {
                    return;
                }

                topSpacer.style.width = `${table.scrollWidth}px`;

                if (topScroll.dataset.scrollBound === '1') {
                    return;
                }

                topScroll.dataset.scrollBound = '1';
                tableScroll.dataset.scrollBound = '1';

                let syncing = false;

                topScroll.addEventListener('scroll', () => {
                    if (syncing) {
                        return;
                    }

                    syncing = true;
                    tableScroll.scrollLeft = topScroll.scrollLeft;
                    syncing = false;
                });

                tableScroll.addEventListener('scroll', () => {
                    if (syncing) {
                        return;
                    }

                    syncing = true;
                    topScroll.scrollLeft = tableScroll.scrollLeft;
                    syncing = false;
                });
            };

            const setLeadImportFooterMode = (mode) => {
                const elements = importElements();

                if (! elements.submit || ! elements.retry) {
                    return;
                }

                if (mode === 'retry') {
                    elements.submit.classList.add('hidden');
                    elements.retry.classList.remove('hidden');

                    return;
                }

                if (mode === 'done') {
                    elements.submit.classList.add('hidden');
                    elements.retry.classList.add('hidden');

                    return;
                }

                elements.retry.classList.add('hidden');
                elements.submit.classList.remove('hidden');
            };

            const renderFailedRows = (rows, sourceId) => {
                const elements = importElements();

                failedImportRows = Array.isArray(rows) ? rows : [];
                importLeadSourceId = sourceId || importLeadSourceId;

                if (! failedImportRows.length) {
                    elements.failed.classList.add('hidden');
                    elements.failedBody.innerHTML = '';
                    elements.failedSummary.textContent = '';

                    return;
                }

                elements.failed.classList.remove('hidden');
                elements.failedSummary.textContent = `${failedImportRows.length} row(s) need fixes before re-import.`;
                elements.failedBody.innerHTML = failedImportRows.map((row, index) => {
                    const data = row.data || {};

                    return `
                        <tr class="bg-white dark:bg-gray-900" data-failed-index="${index}">
                            <td class="whitespace-nowrap px-2 py-2">
                                <button
                                    type="button"
                                    data-remove-index="${index}"
                                    class="secondary-button !min-h-[28px] !px-2 text-[11px]"
                                >
                                    Remove
                                </button>
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 font-semibold text-gray-700 dark:text-gray-200">${escapeHtml(row.row_number)}</td>
                            <td class="min-w-[220px] px-2 py-2 text-red-600 dark:text-red-400">${escapeHtml(row.error || 'Import failed')}</td>
                            ${fieldInput(index, 'companies', data.companies)}
                            ${fieldInput(index, 'lead_value', data.lead_value)}
                            ${fieldInput(index, 'type', data.type)}
                            ${fieldInput(index, 'pricing_type', data.pricing_type)}
                            ${fieldInput(index, 'person_name', data.person_name)}
                            ${fieldInput(index, 'email', data.email)}
                            ${fieldInput(index, 'phone', data.phone)}
                            ${fieldInput(index, 'address', data.address)}
                            ${fieldInput(index, 'city', data.city)}
                            ${fieldInput(index, 'state', data.state)}
                            ${fieldInput(index, 'country', data.country)}
                            ${fieldInput(index, 'postcode', data.postcode)}
                        </tr>
                    `;
                }).join('');

                requestAnimationFrame(() => {
                    syncFailedTableScrollbars();
                });

                setLeadImportFooterMode('retry');
            };

            const collectFailedRowsFromTable = () => {
                const elements = importElements();

                return failedImportRows.map((row, index) => {
                    const data = { ...(row.data || {}) };

                    failedEditFields.forEach((field) => {
                        const input = elements.failedBody.querySelector(
                            `input[data-row-index="${index}"][data-field="${field}"]`
                        );

                        if (input) {
                            data[field] = input.value.trim();
                        }
                    });

                    return {
                        row_number: row.row_number,
                        data,
                    };
                });
            };

            const processImportChunk = async (token, offset) => {
                const response = await window.axios.post(leadImportProcessUrl, {
                    token,
                    offset,
                }, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                return response.data;
            };

            const processImport = async (elements, token, total) => {
                let offset = 0;
                let result = null;

                do {
                    result = await processImportChunk(token, offset);
                    offset = result.processed;

                    const percent = total ? (result.processed / total) * 100 : 100;

                    setProgress(
                        elements,
                        percent,
                        `Imported ${result.processed} of ${total} rows. Created ${result.created}. Skipped duplicates ${result.skipped || 0}.`
                    );
                } while (! result.done);

                const failedCount = result.failed_rows?.length || 0;
                const skippedCount = Number(result.skipped || 0);

                setProgress(
                    elements,
                    100,
                    `${result.message} Processed ${result.processed} of ${total} rows. Created ${result.created}. Skipped duplicates ${skippedCount}. Failed ${failedCount}.`,
                    failedCount > 0
                );

                if (failedCount > 0) {
                    renderFailedRows(result.failed_rows, result.lead_source_id);
                    flash('warning', `${result.message} ${failedCount} row(s) failed. Fix them below and retry.`);
                    elements.submit.disabled = false;
                    elements.submit.classList.remove('opacity-70', 'cursor-not-allowed');
                    setLeadImportFooterMode('retry');

                    return;
                }

                renderFailedRows([], null);
                setLeadImportFooterMode('done');
                flash(skippedCount > 0 ? 'warning' : 'success', result.message);
                setTimeout(() => window.location.reload(), 900);
            };

            document.addEventListener('change', (event) => {
                if (event.target?.id === 'lead-import-tag') {
                    syncLgeColdForwardPanel();
                }

                if (event.target?.id !== 'lead-import-file') {
                    return;
                }

                renderFailedRows([], importLeadSourceId);
                setLeadImportFooterMode('upload');

                const elements = importElements();

                elements.progress?.classList.add('hidden');
            });

            document.addEventListener('submit', async (event) => {
                if (event.target?.id !== 'lead-import-form') {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const elements = importElements();

                if (! elements.file?.files?.length) {
                    flash('error', 'Please select a file to import.');

                    return;
                }

                const sourceSelect = document.getElementById('lead-import-source');

                if (! sourceSelect?.value) {
                    flash('error', 'Please select a lead source for this import.');

                    return;
                }

                const tagSelect = document.getElementById('lead-import-tag');

                if (! tagSelect?.value) {
                    flash('error', 'Please select a tag for this import.');

                    return;
                }

                const selectedAssignees = Array.from(document.querySelectorAll('input[name="assignee_user_ids[]"]:checked'))
                    .map((input) => Number(input.value))
                    .filter((id) => id > 0);
                const industrySelect = document.getElementById('lead-import-industry');

                if (isLgeLeadImport) {
                    const profileSelect = document.getElementById('lead-import-linkedin-profile');

                    if (! profileSelect?.value) {
                        flash('error', 'Please select a LinkedIn working profile for this import.');

                        return;
                    }

                    importLinkedInProfileId = Number(profileSelect.value);
                }

                if (isAdminLeadImport && ! selectedAssignees.length) {
                    flash('error', 'Please select at least one SDR user to assign these leads.');

                    return;
                }

                if (isLgeLeadImport && isColdLeadImportSelected() && ! selectedAssignees.length) {
                    flash('error', 'Please select at least one SDR user to forward cold leads.');

                    return;
                }

                if (isAdminLeadImport && ! industrySelect?.value) {
                    flash('error', 'Please select an industry for this import.');

                    return;
                }

                if (elements.file.files[0].size > leadImportMaxFileSize) {
                    flash('error', 'This file is larger than the current 2 MB PHP upload limit. Please use a split CSV file or increase upload_max_filesize.');

                    return;
                }

                importLeadSourceId = Number(sourceSelect.value);
                importTagId = Number(tagSelect.value);
                importAssigneeUserIds = selectedAssignees;
                importIndustryId = industrySelect?.value ? Number(industrySelect.value) : null;
                renderFailedRows([], importLeadSourceId);

                elements.submit.disabled = true;
                elements.submit.classList.add('opacity-70', 'cursor-not-allowed');

                setProgress(elements, 0, 'Uploading file...');

                const formData = new FormData(elements.form);

                try {
                    const startResponse = await window.axios.post(leadImportStartUrl, formData, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'multipart/form-data',
                        },

                        onUploadProgress(uploadEvent) {
                            if (! uploadEvent.total) {
                                return;
                            }

                            setProgress(elements, (uploadEvent.loaded / uploadEvent.total) * 10, 'Uploading file...');
                        },
                    });

                    const { token, total } = startResponse.data;

                    setProgress(elements, 10, `Prepared ${total} rows. Starting import...`);

                    await processImport(elements, token, total);
                } catch (error) {
                    const response = error.response?.data;
                    const message = response?.errors?.length
                        ? `${response.message || 'Import failed.'} ${response.errors.slice(0, 3).join(' ')}`
                        : response?.message || 'Import failed. Please check the file and try again.';

                    setProgress(elements, 100, 'Import failed.', true);
                    flash('error', message);

                    elements.submit.disabled = false;
                    elements.submit.classList.remove('opacity-70', 'cursor-not-allowed');
                }
            }, true);

            syncLgeColdForwardPanel();

            document.addEventListener('click', async (event) => {
                const removeButton = event.target.closest('#lead-import-failed [data-remove-index]');

                if (removeButton) {
                    event.preventDefault();
                    event.stopPropagation();

                    const index = Number(removeButton.getAttribute('data-remove-index'));

                    if (! Number.isNaN(index)) {
                        removeFailedRow(index);
                    }

                    return;
                }

                if (event.target.closest('#lead-import-remove-all')) {
                    event.preventDefault();
                    event.stopPropagation();
                    removeAllFailedRows();

                    return;
                }

                if (! event.target.closest('#lead-import-retry')) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const elements = importElements();
                const sourceId = importLeadSourceId || Number(document.getElementById('lead-import-source')?.value || 0);

                if (! sourceId) {
                    flash('error', 'Please select a lead source for this import.');

                    return;
                }

                if (! failedImportRows.length) {
                    flash('error', 'There are no failed rows to retry.');

                    return;
                }

                const rows = collectFailedRowsFromTable();

                elements.retry.disabled = true;
                elements.retry.classList.add('opacity-70', 'cursor-not-allowed');
                setProgress(elements, 0, `Retrying ${rows.length} failed row(s)...`);

                try {
                    const response = await window.axios.post(leadImportRetryUrl, {
                        lead_source_id: sourceId,
                        assignee_user_ids: importAssigneeUserIds,
                        industry_id: importIndustryId,
                        import_linkedin_profile_id: importLinkedInProfileId,
                        import_tag_id: importTagId,
                        rows,
                    }, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        validateStatus: (status) => status >= 200 && status < 500,
                    });

                    const result = response.data;
                    const stillFailed = result.failed_rows || [];
                    const skippedCount = Number(result.skipped || 0);

                    renderFailedRows(stillFailed, sourceId);

                    if (stillFailed.length) {
                        setProgress(
                            elements,
                            100,
                            `${result.message || 'Retry finished.'} ${stillFailed.length} row(s) still failing.`,
                            true
                        );
                        flash('warning', `${result.message || 'Retry finished.'} ${stillFailed.length} row(s) still need fixes.`);
                        setLeadImportFooterMode('retry');
                    } else {
                        setProgress(elements, 100, result.message || 'All failed rows imported successfully.');
                        setLeadImportFooterMode('done');
                        flash(skippedCount > 0 ? 'warning' : 'success', result.message || 'All failed rows imported successfully.');
                        setTimeout(() => window.location.reload(), 900);
                    }
                } catch (error) {
                    const message = error.response?.data?.message || 'Retry failed. Please check the rows and try again.';

                    setProgress(elements, 100, 'Retry failed.', true);
                    flash('error', message);
                } finally {
                    const retryButton = document.getElementById('lead-import-retry');

                    if (retryButton) {
                        retryButton.disabled = false;
                        retryButton.classList.remove('opacity-70', 'cursor-not-allowed');
                    }
                }
            });
        </script>
        @endif
