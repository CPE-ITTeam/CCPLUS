import "vuetify/styles"; // Framework layout grids mount natively here
import "vuetify/dist/vuetify-labs.css";
import "@mdi/font/css/materialdesignicons.css";
import { createVuetify } from "vuetify";
import { VSheet } from "vuetify/components/VSheet";

import { createVueI18nAdapter } from "vuetify/locale/adapters/vue-i18n";
import { useI18n } from "vue-i18n";
import {
  en as vuetifyEn,
  fi as vuetifyFi,
  es as vuetifyEs,
  fr as vuetifyFr,
  de as vuetifyDe,
  hu as vuetifyHu,
} from "vuetify/locale";
import i18n from "./i18n";

i18n.global.mergeLocaleMessage("en", { $vuetify: vuetifyEn });
i18n.global.mergeLocaleMessage("fi", { $vuetify: vuetifyFi });
i18n.global.mergeLocaleMessage("es", { $vuetify: vuetifyEs });
i18n.global.mergeLocaleMessage("fr", { $vuetify: vuetifyFr });
i18n.global.mergeLocaleMessage("de", { $vuetify: vuetifyDe });
i18n.global.mergeLocaleMessage("hu", { $vuetify: vuetifyHu });

// ─── REUSABLE INPUT CONFIGURATION GROUP ───
const sharedInputDefaults = {
  hideDetails: "auto", // Hides bottom spacing unless an active error shows
  variant: "outlined", // Gives fields a crisp border frame
  density: "comfortable", // Maximizes input height and breathing room
  persistentPlaceholder: true, // Keeps placeholder text always visible on empty fields
  class: "mb-3", // Adds uniform vertical breathing room below every input
};

// ─── SHARED LIGHT THEME TOKENS ───
const BASE_LIGHT_COLORS = {
  secondary: "#757575", // Neutral gray for secondary layout actions
  surface: "#ffffff", // Solid white for cards and input backgrounds
  background: "#f1f5f9", // Modern light slate gray page background canvas
  success: "#4caf50", // Bright material green for toggle checkmarks
  warning: "#ff9800", // Vivid orange for incomplete rows
  error: "#f44336", // Scarlet red for destructive alerts/deletes
  "on-primary": "#ffffff",
  "on-info": "#ffffff", // Forces text to white inside solid consortium blue blocks
  "on-secondary": "#ffffff", // Forces text to white inside solid gray layout elements
  "on-success": "#ffffff",
  "on-warning": "#212121", // Forces dark charcoal text inside orange alerts for visibility
  "on-error": "#ffffff",
};

const BASE_LIGHT_VARIABLES = {
  "font-family": "sans-serif",
};

export const CONSORTIUM_THEMES = [
  { id: "light", primary: "#0066a1", info: "#3686b4" },
  { id: "emerald", primary: "#0f6e56", info: "#2a8c73" },
  { id: "indigo", primary: "#3730a3", info: "#5550c4" },
  { id: "plum", primary: "#7a2048", info: "#9e3b67" },
  { id: "slate", primary: "#334155", info: "#52637a" },
];

export default createVuetify({
  aliases: {
    CodeBlock: VSheet,
    SectionHeading: VSheet,
  },
  locale: {
    adapter: createVueI18nAdapter({ i18n, useI18n }),
  },
  // ─── NATIVE FRAMEWORK GLOBAL OVERRIDES ───
  defaults: {
    // ─── SHARED INPUT DEFAULTS SELECTION GROUP ───
    // ─── SHARED INPUT DEFAULTS SELECTION GROUP ───
    VAutocomplete: { ...sharedInputDefaults, class: "mb-3 flex-grow-0" },
    VCheckbox: { ...sharedInputDefaults, color: "primary" },
    VCombobox: { ...sharedInputDefaults, class: "mb-3 flex-grow-0" },
    VFileInput: { ...sharedInputDefaults },
    VNumberInput: { ...sharedInputDefaults },
    VRadio: { color: "primary" },
    VRadioGroup: { ...sharedInputDefaults, color: "primary" },
    VSelect: { ...sharedInputDefaults, class: "mb-3 flex-grow-0" },

    VSwitch: {
      ...sharedInputDefaults,
      density: "compact",
      hideDetails: true,
      color: "primary",
      class: "flex-grow-0",
      style: "transform: scale(0.85); transform-origin: left center;",
    },
    VTextField: { ...sharedInputDefaults, clearable: true },
    VTextarea: { ...sharedInputDefaults, clearable: true },

    // ─── FORM LAYOUT SPACING ───
    VForm: {
      VRow: {
        class: "mt-0 mb-1", // Removes extra top margin while keeping bottom row spacing
      },
      VCol: {
        class: "pt-1 pb-2", // Tightens top column padding across dialogs and panels
      },
    },

    // ─── SYSTEM CORE COMPONENTS ───
    CodeBlock: {
      color: "grey-lighten-4",
      rounded: "lg",
      border: true,
      class: "pa-3",
    },
    SectionHeading: {
      tag: "h3",
      color: "transparent",
      class:
        "text-title-large font-weight-semibold text-primary mb-3 d-flex align-center",
    },
    VAlert: {
      variant: "tonal",
      rounded: "lg",
      closeIcon: "mdi-close-circle",
      VBtn: {
        color: undefined,
        variant: "text",
        rounded: "circle",
        size: "default",
        class: "opacity-100",
        VIcon: {
          size: 28,
          class: "opacity-100",
        },
      },
    },

    VAppBar: {
      color: "surface",
      flat: true,
      theme: "light",
      VBtn: {
        color: undefined,
        variant: undefined,
      },
    },
    VBtn: {
      class: "text-uppercase font-weight-medium text-darken-4",
      rounded: "md",
      variant: "tonal",
      color: "primary",
      ripple: false,
      ariaLabel: "Action button",
    },
    VCard: {
      rounded: "lg",
      elevation: 2,
    },
    VCardActions: {
      class: "px-5 pb-4 pt-3 justify-start",
      VBtn: {
        variant: "tonal",
        color: "primary",
      },
    },
    VCardItem: {
      // Modal header strips stay solid dark blue and force inner text templates to pure white
      class: "bg-primary rounded-t px-5 py-3 text-white",
      VBtn: {
        variant: "text",
        color: "white",
        density: "comfortable",
      },
    },
    VCardSubtitle: {
      class: "pt-3 px-5",
    },
    VCardText: {
      class: "pt-3 px-5 pb-5", // Trims top padding below card/dialog headers while keeping side & bottom breathing room
    },

    VCardTitle: {
      color: "primary",
      class: "text-h6 font-weight-semibold pa-0",
    },
    VDataTable: {
      hover: true,
      density: "comfortable",
      VBtn: {
        variant: "text",
        size: "small",
        density: "comfortable",
        rounded: "circle",
        color: undefined,
      },
      VIcon: {
        size: 20,
      },
    },

    VDialog: {
      maxWidth: "600px",
      scrollable: true,
    },
    VExpansionPanels: {
      variant: "accordion",
      rounded: "lg",
    },
    VExpansionPanelTitle: {
      class: "bg-info text-white font-weight-bold",
      style: "font-size: 1.35rem;",
    },
    VList: {
      density: "compact",
      border: true,
      rounded: "lg",
      class: "py-0",
    },
    VTabs: {
      color: "primary",
      bgColor: "blue-grey-lighten-5", // Enforces your unified track background globally
    },
    VTabsWindow: {},
    VTabsWindowItem: {
      transition: false,
      reverseTransition: false,
    },
    VToolbar: {
      color: "primary",
      class: "px-4",
      VBtn: {
        variant: "text",
        color: "white",
      },
    },
    VTooltip: {
      location: "top",
      openDelay: 300,
      closeOnContentClick: true,
      contentClass: "bg-primary text-white",
    },
  },

  theme: {
    defaultTheme: "light",
    themes: {
      light: {
        dark: false,
        colors: {
          ...BASE_LIGHT_COLORS,
          primary: "#0066a1", // Exact dark blue logo token
          info: "#3686b4", // Medium consortium blue drives system info/accents
        },
        variables: BASE_LIGHT_VARIABLES,
      },
      emerald: {
        dark: false,
        colors: {
          ...BASE_LIGHT_COLORS,
          primary: "#0f6e56",
          info: "#2a8c73",
        },
        variables: BASE_LIGHT_VARIABLES,
      },
      indigo: {
        dark: false,
        colors: {
          ...BASE_LIGHT_COLORS,
          primary: "#3730a3",
          info: "#5550c4",
        },
        variables: BASE_LIGHT_VARIABLES,
      },
      plum: {
        dark: false,
        colors: {
          ...BASE_LIGHT_COLORS,
          primary: "#7a2048",
          info: "#9e3b67",
        },
        variables: BASE_LIGHT_VARIABLES,
      },
      slate: {
        dark: false,
        colors: {
          ...BASE_LIGHT_COLORS,
          primary: "#334155",
          info: "#52637a",
        },
        variables: BASE_LIGHT_VARIABLES,
      },
    },
  },
});
