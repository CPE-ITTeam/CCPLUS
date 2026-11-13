// composables/useValidationRules.js
import { useI18n } from 'vue-i18n';

export function useValidationRules() {
  const { t } = useI18n();

  const required = (value) => !!value || t('validation.required');

  const numberRule = (value) =>
    !isNaN(Number(value)) || t('validation.number');

  const emailRule = (value) =>
    !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) || t('validation.email');

  const booleanRule = (value) =>
    typeof value === 'boolean' ? true : t('validation.boolean');

  const yearmon = (value) => {
    if (!value) return t('validation.field_required');
    const regex = /^\d{4}-(0[1-9]|1[0-2])$/;
    return regex.test(value) || t('validation.yearmon');
  };

  return { required, numberRule, emailRule, booleanRule, yearmon };
}
