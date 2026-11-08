/*
 * Set from VITE_OWNER_NAME, VITE_CONTACT_EMAIL and VITE_GOVERNING_STATE. An unset value falls back to a
 * placeholder, marked in the page copy so none ships unnoticed.
 */
export const CONTACT_EMAIL: string =
    import.meta.env.VITE_CONTACT_EMAIL ||
    'CONTACT-ADDRESS@FINAL-DOMAIN (owner to fill in)';
export const GOVERNING_STATE: string =
    import.meta.env.VITE_GOVERNING_STATE || 'STATE (owner to fill in)';
export const OWNER_NAME: string =
    import.meta.env.VITE_OWNER_NAME || 'NAME (owner to fill in)';

export const POLICY_LAST_UPDATED = 'November 7, 2026';
export const ACCESSIBILITY_LAST_REVIEWED = 'November 7, 2026';
