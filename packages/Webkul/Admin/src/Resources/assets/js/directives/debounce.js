/**
 * Debounced "change" dispatcher for search inputs.
 *
 * Usage: v-model.lazy="term" v-debounce="2000"
 * - Waits for typing to pause, then fires a change event (updates lazy v-model).
 * - Empty input clears immediately (no wait).
 * - Enter searches immediately.
 * - Adds `is-pending` class while waiting (for spinner UI).
 */
export default {
    mounted(el, binding) {
        el._debounceDelay = Number(binding.value);

        if (! Number.isFinite(el._debounceDelay) || el._debounceDelay < 0) {
            el._debounceDelay = 2000;
        }

        const flush = () => {
            clearTimeout(el._debounceTimeout);
            el._debounceTimeout = null;
            el.classList.remove('is-pending');
            el.dispatchEvent(new Event('change'));
        };

        const onInput = () => {
            clearTimeout(el._debounceTimeout);

            const value = String(el.value ?? '').trim();

            if (! value) {
                el.classList.remove('is-pending');
                el.dispatchEvent(new Event('change'));

                return;
            }

            el.classList.add('is-pending');

            el._debounceTimeout = setTimeout(() => {
                el._debounceTimeout = null;
                el.classList.remove('is-pending');
                el.dispatchEvent(new Event('change'));
            }, el._debounceDelay);
        };

        const onKeydown = (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            flush();
        };

        el._debounceOnInput = onInput;
        el._debounceOnKeydown = onKeydown;

        el.addEventListener('input', onInput);
        el.addEventListener('keydown', onKeydown);
    },

    updated(el, binding) {
        const delay = Number(binding.value);

        el._debounceDelay = Number.isFinite(delay) && delay >= 0 ? delay : 2000;
    },

    unmounted(el) {
        clearTimeout(el._debounceTimeout);

        if (el._debounceOnInput) {
            el.removeEventListener('input', el._debounceOnInput);
        }

        if (el._debounceOnKeydown) {
            el.removeEventListener('keydown', el._debounceOnKeydown);
        }

        el.classList.remove('is-pending');
    },
};
