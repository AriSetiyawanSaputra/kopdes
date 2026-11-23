/**
 * Generate a RFC 4122 compliant UUID v4 string for POS checkout idempotency_key.
 * Uses native Web Crypto API crypto.randomUUID() with a safe fallback generator.
 *
 * @returns {string}
 */
export function generateUuidV4() {
    if (
        typeof globalThis !== 'undefined' &&
        globalThis.crypto &&
        typeof globalThis.crypto.randomUUID === 'function'
    ) {
        return globalThis.crypto.randomUUID();
    }

    if (
        typeof globalThis !== 'undefined' &&
        globalThis.crypto &&
        typeof globalThis.crypto.getRandomValues === 'function'
    ) {
        const bytes = new Uint8Array(16);
        globalThis.crypto.getRandomValues(bytes);

        // Per RFC 4122 section 4.4: set bits for version 4 and clock_seq_hi_and_reserved
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;

        const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');

        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20, 32)}`;
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = (Math.random() * 16) | 0;
        const value = char === 'x' ? random : (random & 0x3) | 0x8;
        return value.toString(16);
    });
}

export default generateUuidV4;
