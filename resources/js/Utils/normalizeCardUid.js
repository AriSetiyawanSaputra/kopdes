/**
 * Normalize raw RFID/NFC card UID input from USB HID Keyboard Wedge reader:
 * trim whitespace, strip non-alphanumeric characters, and convert to uppercase.
 *
 * @param {string|number|null|undefined} raw
 * @returns {string}
 */
export function normalizeCardUid(raw) {
    if (raw === null || raw === undefined) {
        return '';
    }

    return String(raw)
        .trim()
        .replace(/[^A-Za-z0-9]/g, '')
        .toUpperCase();
}

export default normalizeCardUid;
