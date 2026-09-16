/**
 * Контракт REST API плагина InsurWP.
 *
 * Расчёт целиком на стороне WordPress: приложение только отправляет параметры
 * и отображает готовые предложения.
 */

export interface SelectOption {
  value: string;
  label: string;
}

export interface DateBounds {
  today: string;
  start_min: string;
  start_max: string;
  birth_min: string;
  birth_max: string;
}

export interface OptionsResponse {
  terms: SelectOption[];
  default_term: string;
  territories: SelectOption[];
  limits: SelectOption[];
  show_bgn: boolean;
  show_disclaimer: boolean;
  dates: DateBounds;
}

export interface Offer {
  insurer_key: string;
  insurer_name: string;
  territory: string;
  territory_label: string;
  age_group: string;
  price_eur: number;
  price_bgn: number;
  limit_eur: number | null;
  official_url: string;
  order_url: string;
}

export interface QuoteResponse {
  offers: Offer[];
  age: number;
  term: string;
  term_label: string;
  territory: string;
  date_start: string;
  birth_date: string;
  show_bgn: boolean;
}

export interface QuoteRequest {
  term: string;
  date_start: string;
  birth_date: string;
  territory: string;
}
