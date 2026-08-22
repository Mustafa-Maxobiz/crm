        <script>
            window.copyLeadPhone = async function (button, phone) {
                if (! phone) {
                    return;
                }

                const originalLabel = button.textContent;

                try {
                    if (navigator.clipboard?.writeText) {
                        await navigator.clipboard.writeText(phone);
                    } else {
                        const textarea = document.createElement('textarea');
                        textarea.value = phone;
                        textarea.setAttribute('readonly', '');
                        textarea.style.position = 'fixed';
                        textarea.style.opacity = '0';
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                    }

                    button.textContent = @json(trans('admin::app.leads.index.datagrid.copied'));

                    setTimeout(() => {
                        button.textContent = originalLabel;
                    }, 1500);
                } catch (error) {
                    console.log(error);
                }
            };
        </script>
