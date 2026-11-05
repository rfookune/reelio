import type { DeepReadonly, Ref } from 'vue';
import { nextTick, readonly, ref } from 'vue';

export type UseAnnouncerReturn = {
    message: DeepReadonly<Ref<string>>;
    announce: (text: string) => void;
};

const message = ref('');

/**
 * Speaks a message through the shell's single polite live region. The region
 * is cleared first so repeating the same message is announced again.
 */
export function announce(text: string): void {
    message.value = '';

    void nextTick(() => {
        message.value = text;
    });
}

export function useAnnouncer(): UseAnnouncerReturn {
    return {
        message: readonly(message),
        announce,
    };
}
