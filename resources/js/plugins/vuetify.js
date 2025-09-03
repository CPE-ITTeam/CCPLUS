// resources/js/plugins/vuetify.js
import 'vuetify/styles';
import "vuetify/dist/vuetify-labs.css"
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';
// Create Vuetify instance
const vuetify = createVuetify({
    theme: { defaultTheme: 'light' },
});
export default vuetify;
