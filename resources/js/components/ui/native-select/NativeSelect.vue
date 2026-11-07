<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { ChevronDown } from "@lucide/vue"
import { cn } from "@/lib/utils"

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    /** Classes for the wrapper, which sets the width. Everything else goes to the `<select>`. */
    class?: HTMLAttributes["class"]
    /** The height of a touch target on small screens, as buttons that opt into it have. */
    touch?: boolean
  }>(),
  { touch: false },
)
</script>

<template>
  <div
    data-slot="native-select-wrapper"
    :class="cn('relative w-full', props.class)"
  >
    <select
      v-bind="$attrs"
      data-slot="native-select"
      :class="cn(
        'border-input h-9 w-full cursor-pointer appearance-none rounded-md border bg-surface py-1 pr-9 pl-3 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
        'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
        touch && 'h-11 md:h-9',
      )"
    >
      <slot />
    </select>
    <ChevronDown
      data-slot="native-select-icon"
      class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted"
      aria-hidden="true"
    />
  </div>
</template>
