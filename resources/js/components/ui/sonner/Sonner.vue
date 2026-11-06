<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { CircleCheckIcon, InfoIcon, Loader2Icon, OctagonXIcon, TriangleAlertIcon, XIcon } from "@lucide/vue"
import { Toaster as Sonner } from "vue-sonner"
import { cn } from "@/lib/utils"

import 'vue-sonner/style.css';

const props = defineProps<ToasterProps>()

/*
 * Sonner pauses its timers while the pointer is over the toaster, but not while
 * focus is inside it, so focus is reported to it as the pointer entering and
 * leaving.
 */
function pauseWhileFocused(event: FocusEvent) {
  (event.currentTarget as HTMLElement).dispatchEvent(new MouseEvent("mouseenter"))
}

function resumeWhenFocusLeaves(event: FocusEvent) {
  const toaster = event.currentTarget as HTMLElement

  if (!toaster.contains(event.relatedTarget as Node | null) && !toaster.matches(":hover")) {
    toaster.dispatchEvent(new MouseEvent("mouseleave"))
  }
}
</script>

<template>
  <Sonner
    :class="cn('toaster group', props.class)"
    :style="{
      '--normal-bg': 'var(--surface)',
      '--normal-text': 'var(--foreground)',
      '--normal-border': 'var(--line)',
      '--border-radius': '0.75rem',
    }"
    v-bind="props"
    @focusin="pauseWhileFocused"
    @focusout="resumeWhenFocusLeaves"
  >
    <template #success-icon>
      <CircleCheckIcon class="size-4" />
    </template>
    <template #info-icon>
      <InfoIcon class="size-4" />
    </template>
    <template #warning-icon>
      <TriangleAlertIcon class="size-4" />
    </template>
    <template #error-icon>
      <OctagonXIcon class="size-4" />
    </template>
    <template #loading-icon>
      <div>
        <Loader2Icon class="size-4 animate-spin" />
      </div>
    </template>
    <template #close-icon>
      <XIcon class="size-4" />
    </template>
  </Sonner>
</template>
