import { describe, expect, it } from 'vitest';
import { rankServices } from '@/lib/streamingServiceSearch';

const named = (...names: string[]) => names.map((name) => ({ name }));
const namesOf = (services: { name: string }[]) =>
    services.map((service) => service.name);

describe('searching the streaming services', () => {
    it('ranks a whole name, then its start, then a word in it, then the text inside it', () => {
        const services = named(
            'Fubo Plus',
            'The Plus Plan',
            'Plus',
            'Disney Plus',
            'Plusnet',
        );

        expect(namesOf(rankServices(services, 'plus'))).toEqual([
            'Plus',
            'Plusnet',
            'Fubo Plus',
            'The Plus Plan',
            'Disney Plus',
        ]);
    });

    it('finds the letters in order last, which forgives a missing letter', () => {
        expect(
            namesOf(rankServices(named('Netflix', 'Hulu'), 'netflx')),
        ).toEqual(['Netflix']);
        expect(
            namesOf(rankServices(named('Nextflix', 'Fx Net'), 'net')),
        ).toEqual(['Fx Net', 'Nextflix']);
    });

    it('keeps the source order among names that match equally', () => {
        const services = named('Channel 3', 'Channel 1', 'Channel 2');

        expect(namesOf(rankServices(services, 'channel'))).toEqual([
            'Channel 3',
            'Channel 1',
            'Channel 2',
        ]);
    });

    it('lists no more than the limit, twenty unless told otherwise', () => {
        const services = named(
            ...Array.from({ length: 30 }, (_, i) => `Channel ${i}`),
        );

        expect(rankServices(services, 'channel')).toHaveLength(20);
        expect(rankServices(services, 'channel', 5)).toHaveLength(5);
    });

    it('ignores case, accents and the space around the text', () => {
        const services = named('Médiathèque', 'Canal+', 'Hulu');

        expect(namesOf(rankServices(services, '  MEDIATHEQUE '))).toEqual([
            'Médiathèque',
        ]);
        expect(namesOf(rankServices(services, 'canal'))).toEqual(['Canal+']);
    });

    it('finds the start of a word that has accents or other scripts', () => {
        const services = named('Das Erste Bildung', 'Zattoo');

        expect(namesOf(rankServices(services, 'bild'))).toEqual([
            'Das Erste Bildung',
        ]);
        expect(namesOf(rankServices(named('日本 映画'), '映画'))).toEqual([
            '日本 映画',
        ]);
    });

    it('lists nothing when nothing matches, and the first ones when nothing is typed', () => {
        const services = named('Netflix', 'Hulu');

        expect(rankServices(services, 'zzzz')).toEqual([]);
        expect(rankServices(services, '   ')).toEqual(services);
    });
});
