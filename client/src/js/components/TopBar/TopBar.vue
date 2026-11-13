<template>
    <div v-if="collapsed" ref="tabRef" class="topbar-tab">
        <button
            ref="expandRef"
            type="button"
            class="topbar-tab__btn"
            aria-expanded="false"
            aria-controls="betamaskui-bar"
            @click="expand"
        >
            <img class="topbar-tab__logo" :src="logo" alt="" width="35" height="20" />
            <i class="topbar-tab__arrow font-icon-down-open-big" aria-hidden="true"></i>
            <span class="topbar__sr-only">Show admin tool bar</span>
        </button>
    </div>

    <nav v-else id="betamaskui-bar" ref="barRef" class="topbar" aria-label="Admin tool bar">
        <div class="topbar__brand">
            <button
                ref="collapseRef"
                type="button"
                class="topbar__menu-btn"
                aria-expanded="true"
                aria-controls="betamaskui-bar"
                @click="collapse"
            >
                <img class="topbar__menu-icon" :src="menuIcon" alt="" width="20" height="15" />
                <span class="topbar__sr-only">Hide admin tool bar</span>
            </button>
            <div class="topbar__identity">
                <a class="topbar__logo-link" :href="logoUrl" target="_blank" rel="noopener">
                    <img class="topbar__logo-img" :src="logo" :alt="logoAlt" width="35" height="20" />
                </a>
                <span class="topbar__divider" aria-hidden="true"></span>
                <a class="topbar__name" target="_blank" rel="noopener" :href="siteUrl">{{ appName }}</a>
                <span :class="`topbar__badge topbar__env ${appEnvCss}`">{{ appEnv }}</span>
            </div>
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
                <button
                    v-if="item.id === EDIT_ITEM_ID && currentStage"
                    type="button"
                    :class="`topbar__badge topbar__stage topbar__stage--${currentStage.modifier}`"
                    :title="currentStage.action"
                    :aria-label="`${currentStage.status}. ${currentStage.action}`"
                    @click="showStage(currentStage.switchTo)"
                >
                    {{ currentStage.label }}
                </button>
                <PopupItem
                    @onOpen="setCurrentOpen"
                    :css-id="item.id"
                    :label="item.label"
                    :short-label="item.short_label"
                    :open="isOpen(item.id)"
                    :icon="item.icon"
                    :icon-url="item.icon_url"
                    :mobile-icon-url="item.mobile_icon_url"
                    :caret-icon="caretIcon"
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
    </nav>
</template>
<script setup>
import {
    computed, nextTick, onBeforeUnmount, onMounted, ref, watch,
} from "vue";
import PopupItem from "./PopupItem.vue";
import { BarState, MessageType } from "../../betamask-protocol";

const props = defineProps({
    logo: {
        type: String,
        default: "",
    },
    menuIcon: {
        type: String,
        default: "",
    },
    caretIcon: {
        type: String,
        default: "",
    },
    logoAlt: {
        type: String,
        default: "Silverstripe CMS website (opens in a new tab)",
    },
    logoUrl: {
        type: String,
        default: "https://www.silverstripe.org/",
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
    stage: {
        type: String,
        default: "",
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    items: {
        type: Array,
        default: () => [],
    },
});

const ENV = ["dev", "test", "uat", "prod"];
// The version label sits next to this item
const EDIT_ITEM_ID = "edit";
const STAGES = Object.freeze({
    Stage: {
        label: "Draft",
        modifier: "draft",
        status: "Viewing the draft",
        action: "Switch to published",
        switchTo: "Live",
    },
    Live: {
        label: "Published",
        modifier: "published",
        status: "Viewing the published version",
        action: "Switch to draft",
        switchTo: "Stage",
    },
});
// Room below an open menu for its shadow, in px of the iframe
const MENU_SHADOW_SPACE = 16;

const currentOpen = ref(null);
const collapsed = ref(props.collapsed);
const barRef = ref(null);
const tabRef = ref(null);
const expandRef = ref(null);
const collapseRef = ref(null);
let resizeObserver = null;

const setCurrentOpen = (id) => {
    currentOpen.value = id;
};

const isOpen = (id) => String(id) === String(currentOpen.value);

// Version of the page being viewed, or null when the page is not versioned
const currentStage = computed(() => STAGES[props.stage] || null);

const appEnvCss = computed(() => {
    const env = String(props.appEnv).toLowerCase();
    return ENV.includes(env) ? `topbar__env--${env}` : "";
});

const notifyPage = (message) => {
    if (window.parent !== window) {
        window.parent.postMessage(message, window.location.origin);
    }
};

/**
 * Lowest point of any open menu, so the iframe can grow to show it unclipped
 *
 * @returns {number}
 */
const getOpenMenuBottom = () => {
    const menus = document.querySelectorAll(".popup__content");
    return Array.from(menus).reduce((bottom, menu) => {
        if (!menu.offsetParent) {
            return bottom;
        }
        return Math.max(bottom, menu.getBoundingClientRect().bottom + MENU_SHADOW_SPACE);
    }, 0);
};

/**
 * Tells the page what size the iframe should take for the bar's current state
 */
const reportLayout = () => {
    if (collapsed.value) {
        const tab = tabRef.value?.getBoundingClientRect();
        notifyPage({
            type: MessageType.LAYOUT,
            state: BarState.COLLAPSED,
            width: tab?.width || 0,
            height: tab?.height || 0,
        });
        return;
    }

    const barHeight = barRef.value?.getBoundingClientRect().height || 0;
    const menuBottom = currentOpen.value ? getOpenMenuBottom() : 0;
    notifyPage({
        type: MessageType.LAYOUT,
        state: menuBottom > barHeight ? BarState.MENU : BarState.EXPANDED,
        width: null,
        height: Math.max(barHeight, menuBottom),
    });
};

const observe = () => {
    resizeObserver?.disconnect();
    const element = collapsed.value ? tabRef.value : barRef.value;
    if (element && resizeObserver) {
        resizeObserver.observe(element);
    }
};

const collapse = async () => {
    setCurrentOpen(null);
    collapsed.value = true;
    await nextTick();
    observe();
    reportLayout();
    expandRef.value?.focus();
};

const expand = async () => {
    collapsed.value = false;
    await nextTick();
    observe();
    reportLayout();
    collapseRef.value?.focus();
};

const showStage = (stage) => {
    notifyPage({ type: MessageType.STAGE, stage });
};

/**
 * Escape closes an open menu, or collapses the bar when no menu is open
 *
 * @param {KeyboardEvent} e
 */
const onKeydown = (e) => {
    if (e.key !== "Escape" || collapsed.value) {
        return;
    }
    e.preventDefault();
    if (currentOpen.value) {
        const trigger = document.querySelector(`[data-item="${currentOpen.value}"]`);
        setCurrentOpen(null);
        trigger?.focus();
        return;
    }
    collapse();
};

/**
 * A click on the page outside the bar happens in another document; the bar only
 * sees its own window lose focus, which closes any open menu.
 */
const onWindowBlur = () => setCurrentOpen(null);

watch(currentOpen, () => nextTick(reportLayout));

onMounted(() => {
    resizeObserver = new ResizeObserver(reportLayout);
    observe();
    reportLayout();
    document.addEventListener("keydown", onKeydown);
    window.addEventListener("blur", onWindowBlur);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    document.removeEventListener("keydown", onKeydown);
    window.removeEventListener("blur", onWindowBlur);
});
</script>
