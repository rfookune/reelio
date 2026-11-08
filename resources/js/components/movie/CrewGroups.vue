<script setup lang="ts">
import { computed } from 'vue';
import { crewGroups } from '@/lib/crew';
import type { CrewMember } from '@/lib/crew';

/** The people who directed, wrote and produced the movie, as text under their group, each with their job. */
const props = defineProps<{ crew: CrewMember[] }>();

const groups = computed(() => crewGroups(props.crew));
</script>

<template>
    <section v-if="groups.length > 0" aria-labelledby="crew">
        <h2 id="crew" class="mb-6 text-2xl font-semibold tracking-tight">
            Crew
        </h2>
        <div class="flex flex-col gap-8">
            <div
                v-for="group in groups"
                :key="group.title"
                :data-slot="`crew-${group.title.toLowerCase()}`"
            >
                <h3
                    class="mb-4 text-sm font-medium tracking-wide text-muted uppercase"
                >
                    {{ group.title }}
                </h3>
                <ul class="columns-1 gap-8 text-sm sm:columns-2 lg:columns-3">
                    <li
                        v-for="person in group.people"
                        :key="person.id"
                        class="break-inside-avoid py-0.5"
                    >
                        <span class="font-medium">{{ person.name }}</span>
                        <span class="text-muted">
                            · {{ person.jobs.join(', ') }}</span
                        >
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
