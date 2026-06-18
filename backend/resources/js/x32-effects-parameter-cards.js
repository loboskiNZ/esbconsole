function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function setInputState(input, state) {
    input.classList.remove('is-saving', 'is-saved', 'is-error');

    if (state) {
        input.classList.add(state);
    }
}

async function saveParameterInput(input) {
    const url = input.dataset.updateUrl;

    if (!url) {
        return;
    }

    const value = input.value.trim();
    const previousValue = input.dataset.confirmedValue ?? '';

    if (value === previousValue) {
        return;
    }

    setInputState(input, 'is-saving');

    try {
        const response = await fetch(url, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ value: value === '' ? null : value }),
        });

        if (!response.ok) {
            throw new Error('save failed');
        }

        const payload = await response.json();
        const savedValue = payload?.parameter?.value ?? '';

        input.value = savedValue;
        input.dataset.confirmedValue = savedValue;
        setInputState(input, 'is-saved');
        window.setTimeout(() => setInputState(input, null), 900);
    } catch {
        input.value = previousValue;
        setInputState(input, 'is-error');
        window.setTimeout(() => setInputState(input, null), 1200);
    }
}

function initEffectsParameterCards() {
    document.querySelectorAll('[data-effect-parameter-input]').forEach((input) => {
        input.dataset.confirmedValue = input.value.trim();

        if (input.tagName === 'SELECT') {
            input.addEventListener('change', () => saveParameterInput(input));
            return;
        }

        input.addEventListener('blur', () => saveParameterInput(input));
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                input.blur();
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', initEffectsParameterCards);
