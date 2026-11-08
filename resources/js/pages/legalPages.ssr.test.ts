import { renderToString } from '@vue/server-renderer';
import { createSSRApp } from 'vue';
import type { Component } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import Accessibility from '@/pages/Accessibility.vue';
import About from '@/pages/About.vue';
import Privacy from '@/pages/Privacy.vue';
import Terms from '@/pages/Terms.vue';
import Register from '@/pages/auth/Register.vue';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        props: {
            name: 'Reelio',
            auth: { user: null },
            seo: {
                url: 'https://reelio.test/',
                card: 'https://reelio.test/c.png',
            },
        },
    }),
    Head: { render: () => null },
    Form: {
        setup:
            (_: unknown, { slots }: { slots: Record<string, any> }) =>
            () =>
                slots.default?.({ errors: {}, processing: false }),
    },
    Link: {
        props: ['href'],
        template: '<a :href="href.url ?? href"><slot /></a>',
    },
}));

async function render(component: Component, props = {}): Promise<string> {
    const app = createSSRApp(component, props);
    app.directive('focus', {});

    return renderToString(app);
}

describe('the public pages', () => {
    it('About credits TMDB, JustWatch and IMDb', async () => {
        const html = await render(About);

        expect(html).toContain('alt="TMDB"');
        expect(html).toContain('JustWatch');
        expect(html).toContain('IMDb');
    });

    it('Privacy lists each required section and the dated heading', async () => {
        const html = await render(Privacy);

        for (const text of [
            'In plain English',
            'Last updated',
            'Cookies',
            'country',
            'How long we keep it',
            'Who else receives data',
            'YouTube trailers',
            'Backups',
            'Access logs',
            'representative',
            'United States',
        ]) {
            expect(html).toContain(text);
        }
        expect(html.match(/<h1/g)).toHaveLength(1);
    });

    it('Terms covers the required clauses', async () => {
        const html = await render(Terms);

        for (const text of [
            'permanently non-commercial',
            '30 days',
            'scrape',
            '13 or older',
            'Limited liability',
            'Last updated',
        ]) {
            expect(html).toContain(text);
        }
    });

    it('Accessibility states the target, review date and contact', async () => {
        const html = await render(Accessibility);

        expect(html).toContain('WCAG');
        expect(html).toContain('2.2');
        expect(html).toContain('Last reviewed');
        expect(html).toContain('owner-placeholder');
    });

    it('Register shows the Terms line with links and no checkbox', async () => {
        const html = await render(Register, { passwordRules: '' });

        expect(html).toContain('By creating an account you agree to the');
        expect(html).toContain('href="/terms"');
        expect(html).toContain('href="/privacy"');
        expect(html).toContain('13 or older');
        expect(html).not.toContain('checkbox');
    });
});
