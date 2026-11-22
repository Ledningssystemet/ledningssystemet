export function flattenErrors(errorBag) {
    if (!errorBag || typeof errorBag !== 'object') {
        return [];
    }

    return Object.values(errorBag).flatMap((messages) => (
        Array.isArray(messages) ? messages : [messages]
    )).filter(Boolean);
}

export function buildFormPayload(form) {
    const formData = new FormData(form);
    const payload = {};

    for (const [key, value] of formData.entries()) {
        payload[key] = value;
    }

    return payload;
}

