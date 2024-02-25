<template>
    <div class="topbar">
        <div class="topbar__brand">
            <div class="topbar__logo">
                <a class="topbar__logo-link" :href="logoUrl">
                    <img class="topbar__logo-img" :src="logo" :alt="logoAlt" />
                </a>
            </div>
            <div class="topbar__divider"></div>
            <a class="topbar__name" target="_blank" href="/">{{ appName }}</a>
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
import { onMounted, ref, computed } from "vue";
import PopupItem from "./PopupItem.vue";

// Props for the component
const props = defineProps({
    logo: {
        type: String,
        default: "",
    },
    logoAlt: {
        type: String,
        default: "Silverstripe CMS",
    },
    logoUrl: {
        type: String,
        default: "https://silverstripe.org/",
    },
    appName: {
        type: String,
        default: "Silverstripe",
    },
    appVersion: {
        type: String,
        default: "v5.x",
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

onMounted(() => {
    // On mount we listen to event from the iframe to update URLs from topbar (i.e. update edit page)
    window.document.addEventListener(
        "betamaskTopBarLoaded",
        (e) => {
            if (e.detail?.urls) {
                Object.keys(e.detail.urls).forEach((key) => {
                    document
                        .querySelector(key)
                        .setAttribute("href", e.detail.urls[key]);
                });
            }

            // Close all opened menu items if event received from iframe
            if (e.detail?.close) {
                setCurrentOpen(null);
            }

            // Update browser address bar
            if (e.detail?.history) {
                window.history.replaceState(
                    e.detail.history,
                    e.detail.history.title,
                    e.detail.history.url
                );
                document.title = e.detail.history.title;
            }
        },
        false
    );
});
</script>
