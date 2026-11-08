export type CrewMember = {
    id: number;
    name: string;
    job: string;
};

export type CrewGroup = {
    title: string;
    people: { id: number; name: string; jobs: string[] }[];
};

const GROUPS: { title: string; jobs: string[] }[] = [
    { title: 'Directing', jobs: ['Director'] },
    { title: 'Writing', jobs: ['Writer', 'Screenplay', 'Story', 'Novel'] },
    { title: 'Producing', jobs: ['Producer', 'Executive Producer'] },
];

/** The crew in groups, each person once per group with all their jobs there, in the order the crew arrived. Empty groups are left out. */
export function crewGroups(crew: CrewMember[]): CrewGroup[] {
    return GROUPS.map(({ title, jobs }) => {
        const people = new Map<number, CrewGroup['people'][number]>();

        for (const member of crew.filter(({ job }) => jobs.includes(job))) {
            const person = people.get(member.id) ?? {
                id: member.id,
                name: member.name,
                jobs: [],
            };
            person.jobs.push(member.job);
            people.set(member.id, person);
        }

        return { title, people: [...people.values()] };
    }).filter(({ people }) => people.length > 0);
}
