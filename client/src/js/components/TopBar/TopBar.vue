<template>
    <div class="topbar">
        <div class="topbar__brand">
            <div class="topbar__logo">
                <a class="topbar__logo-link" :href="logoUrl">
                    <img class="topbar__logo-img" :src="logo" :alt="logoAlt" />
                </a>
            </div>
            <div class="topbar__divider"></div>
            <a class="topbar__name" target="_blank" rel="noopener" :href="siteUrl">{{ appName }}</a>
            <span :class="`topbar__env ${appEnvCss}`">{{ appEnv }}</span>
        </div>

        <div class="topbar__actions">
            <div
                v-for="item in items"
                :key="item.id"
                :class="{
                    topbar__action: true,
                    [`topbar__action--${item.css_modifier}`]: item.css_modifier,
                }"
            >
                <PopupItem
                    @onOpen="setCurrentOpen"
                    :css-id="item.id"
                    :label="item.label"
                    :open="isOpen(item.id)"
                    :icon="item.icon"
                    :link="item.link"
                    :target="item.target"
                    :items="item.items"
                >
                    <template v-if="item.heading" #heading>
                        <div v-html="item.heading"></div>
                    </template>
                </PopupItem>
            </div>
        </div>
    </div>
</template>
<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import PopupItem from "./PopupItem.vue";

// Props for the component
const props = defineProps({
    logo: {
        type: String,
        default: "",
    },
    logoAlt: {
        type: String,
        default: "Go to the CMS",
    },
    logoUrl: {
        type: String,
        default: "https://silverstripe.org/",
    },
    siteUrl: {
        type: String,
        default: "/",
    },
    appName: {
        type: String,
        default: "Silverstripe",
    },
    appEnv: {
        type: String,
        default: "dev",
    },
    items: {
        type: Array,
        default: () => [],
    },
});

// Reference current opened item
const currentOpen = ref(null);
// List of supported environment key
const ENV = ["dev", "test", "uat", "prod"];

/**
 * Method to set current opened item
 * @param id
 */
const setCurrentOpen = (id) => {
    currentOpen.value = id;
};

/**
 * Whether or not an item is opened. Value also passed to child component
 *
 * @param id
 * @returns {boolean}
 */
const isOpen = (id) => String(id) === String(currentOpen.value);

const appEnvCss = computed(() => {
    const env = String(props.appEnv).toLowerCase();
    return ENV.includes(env) ? `topbar__env--${env}` : "";
});

/**
 * Applies a message from the page in the iframe (see betamask-inline.js)
 *
 * @param {CustomEvent} e
 */
const onFrameMessage = (e) => {
    const detail = e.detail || {};

    Object.entries(detail.urls || {}).forEach(([selector, url]) => {
        document.querySelector(selector)?.setAttribute("href", url);
    });

    if (detail.close) {
        setCurrentOpen(null);
    }

    if (detail.history?.url) {
        window.history.replaceState(null, "", detail.history.url);
        document.title = detail.history.title || document.title;
    }
};

onMounted(() => {
    document.addEventListener("betamaskTopBarLoaded", onFrameMessage);
});

onBeforeUnmount(() => {
    document.removeEventListener("betamaskTopBarLoaded", onFrameMessage);
});
</script>
