<template>
    <div class="popup" ref="popupRef">
        <component
            :is="component"
            class="popup__btn"
            :aria-controls="isLink ? null : props.cssId"
            :type="isLink ? null : 'button'"
            :aria-expanded="isLink ? null : String(isOpen)"
            @click="toggle"
            @keydown.esc.prevent="forceClose"
            @keydown.space.prevent="handleKeyDownSpace"
            @blur.prevent="close"
            :href="props.link"
            :data-item="props.cssId"
        >
            <i
                v-if="icon"
                :class="{
                    [icon]: true,
                    popup__icon: true,
                }"
            ></i>
            <span class="popup__label">{{ label }}</span>
            <i
                v-if="!isLink"
                :class="{
                    'font-icon-caret-down-two': true,
                    popup__arrow: true,
                    'popup__arrow--open': isOpen,
                }"
            ></i>
        </component>

        <div
            v-if="items?.length || hasSlot('heading')"
            v-show="isOpen"
            :id="props.cssId"
            class="popup__content"
        >
            <ul class="popup__menu">
                <template v-if="hasSlot('heading')">
                    <li class="popup__item">
                        <slot name="heading"></slot>
                    </li>
                    <li class="popup__item popup__item--divider"></li>
                </template>

                <li
                    v-for="item in items"
                    :key="item.title"
                    class="popup__item popup__item--black"
                >
                    <a
                        class="popup__link"
                        :href="item.link"
                        :target="item.target"
                        rel="noopener noreferrer"
                        >{{ item.label }}</a
                    >
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, useSlots } from "vue";
import { onClickOutside } from "@vueuse/core";

// Get reference to slots
const slots = useSlots();
// Define events to emit
const emit = defineEmits(["onOpen"]);
// Component props
const props = defineProps({
    cssId: {
        type: String,
        required: true,
    },
    icon: {
        type: String,
        default: "",
    },
    label: {
        type: String,
        required: true,
    },
    link: {
        type: String,
        default: null,
    },
    items: {
        type: Array,
        default: () => [],
    },
    heading: {
        type: String,
        default: "",
    },
    open: {
        type: Boolean,
        default: false,
    },
});

// DOM reference for entire component. Use for click outside
const popupRef = ref(null);
// Internal reference for when component open/close
const openInternal = ref(props.open);
// Reference to indicate that the content is opened. Used for keyboard action
const keepOpen = ref(false);

/**
 * Whether or not a specific slot is used
 *
 * @param name
 * @returns {boolean}
 */
const hasSlot = (name) => !!slots[name];

/**
 * Force closing the content and emit an event to parent
 */
const forceClose = () => {
    openInternal.value = false;
    keepOpen.value = false;
    emit("onOpen", null);
};

/**
 * Attempt to close content. It prevents closing if keep open is true
 */
const close = (e) => {
    if (keepOpen.value) {
        return;
    }

    // If menu item clicked prevent the close to allow for native click to function
    if (e.relatedTarget?.className === "popup__link") {
        return;
    }

    forceClose();
};

/**
 * Toggle the content of the popup one/off and emit event
 */
const toggle = () => {
    openInternal.value = !openInternal.value;
    emit("onOpen", !openInternal.value ? null : props.cssId);
};

/**
 * Event callback if the popup trigger click using space key
 */
const handleKeyDownSpace = () => {
    openInternal.value = !openInternal.value;
    keepOpen.value = openInternal.value;
    emit("onOpen", !openInternal.value ? null : props.cssId);
};

/**
 * Whether or not the content is opened/closed
 *
 * @type {ComputedRef<unknown>}
 */
const isOpen = computed(() => openInternal.value || props.open);

/**
 * Check if the component is for a link
 *
 * @type {ComputedRef<any>}
 */
const isLink = computed(() => props.link);

/**
 * Whether to use a link or a button for trigger item. Button for dropdown
 *
 * @type {ComputedRef<string>}
 */
const component = computed(() => (isLink.value ? "a" : "button"));

// If there is a click outside the component then force close the popup
onClickOutside(popupRef, () => forceClose());
</script>
