<script setup lang="ts">
defineProps<{
    caption: string;
    headings: string[];
    rows: string[][];
}>();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm">
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead>
                <tr class="border-b border-line">
                    <th
                        v-for="heading in headings"
                        :key="heading"
                        scope="col"
                        class="py-2 pr-4 font-semibold"
                    >
                        {{ heading }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row[0]"
                    class="border-b border-line align-top"
                >
                    <th scope="row" class="py-2 pr-4 font-medium">
                        {{ row[0] }}
                    </th>
                    <td
                        v-for="(cell, index) in row.slice(1)"
                        :key="index"
                        class="py-2 pr-4"
                    >
                        {{ cell }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
