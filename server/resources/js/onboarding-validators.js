export function normalizeUsername(value) {
    return value.trim().toLowerCase();
}

export function validateUsername(value) {
    const username = normalizeUsername(value);

    if (username.length < 3 || username.length > 32) {
        return 'Username must be 3–32 characters.';
    }

    if (!/^[a-z0-9]+$/.test(username)) {
        return 'Username may only contain letters and numbers — no spaces or symbols.';
    }

    return '';
}

export function validatePassword(value) {
    if (value.length < 8 || value.length > 50) {
        return 'Password must be 8–50 characters.';
    }

    if (!/[A-Z]/.test(value)) {
        return 'Password must include at least one uppercase letter.';
    }

    if (!/[a-z]/.test(value)) {
        return 'Password must include at least one lowercase letter.';
    }

    if (!/[0-9]/.test(value)) {
        return 'Password must include at least one number.';
    }

    if (!/[^A-Za-z0-9]/.test(value)) {
        return 'Password must include at least one symbol.';
    }

    return '';
}

export function validateEmail(value) {
    const email = value.trim();

    if (!email) {
        return 'Email address is required.';
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return 'Enter a valid email address.';
    }

    return '';
}

export function validateRequired(value, label) {
    if (!value.trim()) {
        return `${label} is required.`;
    }

    return '';
}
