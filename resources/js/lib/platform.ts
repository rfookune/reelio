/** Whether the visitor's computer is an Apple one, where the Command key does what Control does elsewhere. */
export function onApplePlatform(): boolean {
    const platform =
        (navigator as Navigator & { userAgentData?: { platform?: string } })
            .userAgentData?.platform ?? navigator.platform;

    /** `userAgentData.platform` says "macOS" and `navigator.platform` "MacIntel", so the match ignores case. */
    return /mac|iphone|ipad|ipod/i.test(platform);
}
