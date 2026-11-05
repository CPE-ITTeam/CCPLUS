// resources/js/plugins/vuetify.js
import "vuetify/styles"; // Framework layout grids mount natively here
import "vuetify/dist/vuetify-labs.css";
import "@mdi/font/css/materialdesignicons.css";
import { createVuetify } from "vuetify";

// ─── REUSABLE INPUT CONFIGURATION GROUP ───
const sharedInputDefaults = {
  hideDetails: "auto", // Hides bottom spacing unless an active error shows
  variant: "outlined", // Gives fields a crisp border frame
  density: "comfortable", // Reduces spacing slightly compared to default (larger than 'compact')
  clearable: true, // GLOBAL DEFAULT: Adds the "X" clear icon to all inputs
  persistentPlaceholder: true, // Keeps placeholder text always visible on empty fields
};

export default createVuetify({
  // ─── NATIVE FRAMEWORK GLOBAL OVERRIDES ───
  // resources/js/plugins/vuetify.js
  // Inside your resources/js/plugins/vuetify.js configuration layout
  defaults: {
    // ─── SHARED INPUT DEFAULTS SELECTION GROUP ───
    VAutocomplete: { ...sharedInputDefaults },
    VCheckbox: { ...sharedInputDefaults },
    VCombobox: { ...sharedInputDefaults },
    VFileInput: { ...sharedInputDefaults },
    VSelect: { ...sharedInputDefaults },
    VSwitch: { ...sharedInputDefaults },
    VTextField: { ...sharedInputDefaults },
    VTextarea: { ...sharedInputDefaults },

    // ─── SYSTEM CORE COMPONENTS ───
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
      class: "px-4 pb-4 justify-start",
      VBtn: {
        variant: "tonal",
        color: "primary",
      },
    },
    VCardItem: {
      // 💡 Modal header strips stay solid dark blue and force inner text templates to pure white
      class: "bg-primary rounded-t pa-3 text-white", 
    },
    VCardText: {
      class: "pa-4",
    },
    VCardTitle: {
      // 💡 FIXED: Removed 'text-primary' so it inherits white context inside popups, 
      // while allowing your page.vue views to manage standalone styling!
      class: "px-4 pt-4 text-primary font-weight-semibold",     },
    VDialog: {
      maxWidth: "600px",
    },
    VList: {
      density: "compact",
      border: true,
      rounded: "lg",
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
    },
  },


  theme: {
    defaultTheme: "light",
    themes: {
      light: {
        dark: false,
        colors: {
          // ─── ⚓ CORE BRAND ARCHITECTURE ───
          primary: "#0066a1", // Exact dark blue logo token
          info: "#3686b4", // 💡 SHIFTED: Your medium consortium blue now drives system info/accents!
          secondary: "#757575", // 💡 NEW: A crisp, professional neutral gray for secondary layout actions
          surface: "#ffffff", // Solid white for cards and input backgrounds
          background: "#f1f5f9", // Modern light slate gray page background canvas

          // ─── 🎨 VIBRANT HIGH-VISIBILITY FEEDBACK COLORS ───
          success: "#4caf50", // Bright material green for toggle checkmarks
          warning: "#ff9800", // Vivid orange for incomplete rows
          error: "#f44336", // Scarlet red for destructive alerts/deletes

          // ─── ♿ FOREGROUND TEXT CONTRAST ENGINES ───
          "on-primary": "#ffffff",
          "on-info": "#ffffff", // Forces text to white inside solid consortium blue blocks
          "on-secondary": "#ffffff", // Forces text to white inside solid gray layout elements
          "on-success": "#ffffff",
          "on-warning": "#212121", // Forces dark charcoal text inside orange alerts for visibility
          "on-error": "#ffffff",
        },
        variables: {
          "font-family": "sans-serif",
        },
      },
    },
  },
});
