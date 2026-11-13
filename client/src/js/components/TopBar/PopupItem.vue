<template>
    <div class="popup" ref="popupRef" @focusout="onFocusOut">
        <component
            :is="component"
            class="popup__btn"
            :aria-controls="isLink ? null : menuId"
            :type="isLink ? null : 'button'"
            :aria-expanded="isLink ? null : String(open)"
            @click="toggle"
            :href="link"
            :target="isLink ? target : null"
            :rel="isLink && target === '_blank' ? 'noopener' : null"
            :data-item="cssId"
        >
            <span v-if="iconUrl || icon" class="popup__icon" aria-hidden="true">
                <img
                    v-if="iconUrl"
                    :class="{ 'popup__icon-img': true, 'popup__icon-img--wide': mobileIconUrl }"
                    :src="iconUrl"
                    alt=""
                    width="22"
                    height="22"
                />
                <img
                    v-if="mobileIconUrl"
                    class="popup__icon-img popup__icon-img--mobile"
                    :src="mobileIconUrl"
                    alt=""
                />
                <i v-if="!iconUrl && icon" :class="['popup__font-icon', icon]"></i>
            </span>
            <span :class="{ popup__label: true, 'popup__label--has-short': shortLabel }">{{ label }}</span>
            <span v-if="shortLabel" class="popup__label popup__label--short" aria-hidden="true">
                {{ shortLabel }}
            </span>
            <img
                v-if="!isLink && caretIcon"
                :class="{ popup__caret: true, 'popup__caret--open': open }"
                :src="caretIcon"
                alt=""
                width="14"
                height="14"
            />
        </component>

        <div
            v-if="items?.length || hasSlot('heading')"
            v-show="open"
            :id="menuId"
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
                    :key="item.link"
                    class="popup__item popup__item--black"
                >
                    <a
                        class="popup__link"
                        :href="item.link"
                        :target="item.target"
                        :rel="item.target === '_blank' ? 'noopener' : null"
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

const slots = useSlots();
// Asks the bar to open this item (its id) or to close it (null)
const emit = defineEmits(["onOpen"]);
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
    // Shown instead of the label on narrow screens; the label stays the accessible name
    shortLabel: {
        type: String,
        default: "",
    },
    iconUrl: {
        type: String,
        default: "",
    },
    // Replaces iconUrl on phones, where buttons show only their icon
    mobileIconUrl: {
        type: String,
        default: "",
    },
    caretIcon: {
        type: String,
        default: "",
    },
    link: {
        type: String,
        default: null,
    },
    target: {
        type: String,
        default: null,
    },
    items: {
        type: Array,
        default: () => [],
    },
    // Open state is owned by the bar, so only one menu is open at a time
    open: {
        type: Boolean,
        default: false,
    },
});

const popupRef = ref(null);

const hasSlot = (name) => !!slots[name];

const isLink = computed(() => !!props.link);

const component = computed(() => (isLink.value ? "a" : "button"));

// DOM id of the dropdown, namespaced so item ids cannot clash with other elements
const menuId = computed(() => `betamaskui-menu-${props.cssId}`);

const close = () => {
    if (props.open) {
        emit("onOpen", null);
    }
};

const toggle = () => {
    if (!isLink.value) {
        emit("onOpen", props.open ? null : props.cssId);
    }
};

/**
 * Closes the menu once keyboard focus moves to another element of the bar.
 * Focus moving nowhere (e.g. Safari on mouse clicks) is left to click-outside,
 * so a click on a menu link is not cancelled by the menu closing first.
 *
 * @param {FocusEvent} e
 */
const onFocusOut = (e) => {
    if (e.relatedTarget && !popupRef.value?.contains(e.relatedTarget)) {
        close();
    }
};

onClickOutside(popupRef, close);
</script>
