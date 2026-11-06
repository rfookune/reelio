<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { CircleCheckIcon, InfoIcon, Loader2Icon, OctagonXIcon, TriangleAlertIcon, XIcon } from "@lucide/vue"
import { onBeforeUnmount, onMounted } from "vue"
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

/*
 * Sonner's Alt+T focuses its list, but vue-sonner 2.0.9 then sends focus
 * straight back to the element that had it, so the hotkey never reaches a
 * toast. The wrapper handles Alt+T itself, before Sonner sees it: focus moves
 * to the front toast's action (such as Undo), else the toast, and Escape inside
 * the toaster returns it to where it was.
 */
let focusedBeforeToaster: HTMLElement | null = null

function focusFrontToast(event: KeyboardEvent) {
  if (event.altKey && event.code === "KeyT") {
    const toast = document.querySelector<HTMLElement>('[data-sonner-toast][data-front="true"]')

    if (toast === null) {
      return
    }

    event.preventDefault()
    event.stopPropagation()
    focusedBeforeToaster = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    (toast.querySelector<HTMLElement>("[data-action]") ?? toast).focus()

    return
  }

  if (event.key === "Escape" && document.activeElement?.closest("[data-sonner-toaster]") && focusedBeforeToaster?.isConnected) {
    focusedBeforeToaster.focus()
    focusedBeforeToaster = null
  }
}

onMounted(() => window.addEventListener("keydown", focusFrontToast, { capture: true }))
onBeforeUnmount(() => window.removeEventListener("keydown", focusFrontToast, { capture: true }))
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
