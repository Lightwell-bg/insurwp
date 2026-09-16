/**
 * Ссылки на платные услуги bginfo.eu.
 *
 * Калькулятор — бесплатный первый шаг; здесь то, что нужно дальше на пути
 * переезда. Список и формулировки поддерживаются вручную вместе с сайтом.
 */

export interface ServiceLink {
  label: string;
  url: string;
}

export type ServiceAccent = 'rose' | 'honey' | 'pine';

export interface ServiceGroup {
  id: string;
  title: string;
  accent: ServiceAccent;
  icon: 'id-card' | 'star' | 'briefcase';
  links: ServiceLink[];
}

export const SERVICE_GROUPS: ServiceGroup[] = [
  {
    id: 'residency',
    title: 'Вид на жительство',
    accent: 'rose',
    icon: 'id-card',
    links: [
      { label: 'С чего начать', url: 'https://bginfo.eu/vid-na-zhitelstvo/' },
      { label: 'Виза Д (иммиграционная)', url: 'https://bginfo.eu/vid-na-zhitelstvo/viza-d-immigratsionnaya/' },
      { label: 'ВНЖ', url: 'https://bginfo.eu/vid-na-zhitelstvo/vnzh/' },
      { label: 'ПМЖ (ДВЖ)', url: 'https://bginfo.eu/vid-na-zhitelstvo/pmzh-dvzh/' },
      {
        label: 'Для пенсионеров',
        url: 'https://bginfo.eu/vid-na-zhitelstvo/vid-na-zhitelstvo-v-bolgarii-dlya-pensionerov/',
      },
      { label: 'Открытие представительства', url: 'https://bginfo.eu/otkrytie-predstavitelstva/' },
      { label: 'Синяя карта ЕС', url: 'https://bginfo.eu/vid-na-zhitelstvo/eu-blue-card/' },
      {
        label: 'Для членов семьи',
        url: 'https://bginfo.eu/vid-na-zhitelstvo/vid-na-zhitelstvo-dlya-chlenov-semi/',
      },
      {
        label: 'Виза для цифровых кочевников',
        url: 'https://bginfo.eu/vid-na-zhitelstvo/viza-dlya-cifrovyx-kochevnikov/',
      },
    ],
  },
  {
    id: 'citizenship',
    title: 'Гражданство',
    accent: 'honey',
    icon: 'star',
    links: [
      {
        label: 'Гражданство по происхождению',
        url: 'https://bginfo.eu/poluchenie-bolgarskogo-grazhdanstva-po-proisxozhdeniyu/',
      },
    ],
  },
  {
    id: 'business',
    title: 'Бизнес в ЕС',
    accent: 'pine',
    icon: 'briefcase',
    links: [
      { label: 'С чего начать', url: 'https://bginfo.eu/biznes-v-bolgarii-i-es/' },
      { label: 'Открытие фирмы', url: 'https://bginfo.eu/biznes-v-bolgarii-i-es/otkrytie-firmy-v-bolgarii/' },
      {
        label: 'ДПК (общество с переменным капиталом)',
        url: 'https://bginfo.eu/biznes-v-bolgarii-i-es/obshhestvo-s-peremennym-kapitalom-v-bolgarii/',
      },
      { label: 'Бизнес-инкубатор', url: 'https://bginfo.eu/biznes-v-bolgarii-i-es/biznes-inkubator-v-bolgarii/' },
      { label: 'Налоги для фирм', url: 'https://bginfo.eu/biznes-v-bolgarii-i-es/nalogi/' },
      {
        label: 'Годовая отчётность',
        url: 'https://bginfo.eu/biznes-v-bolgarii-i-es/godovaya-otchetnost-firm-v-bolgarii/',
      },
      { label: 'Закрытие фирмы', url: 'https://bginfo.eu/biznes-v-bolgarii-i-es/zakrytie-firmy-v-bolgarii/' },
      {
        label: 'Анкета для открытия фирмы',
        url: 'https://bginfo.eu/biznes-v-bolgarii-i-es/anketa-dlya-otkrytiya-firmy-v-bolgarii/',
      },
    ],
  },
];

export const SERVICE_CTA = {
  title: 'Сколько это будет стоить?',
  prices: { label: 'Смотреть цены на услуги', url: 'https://bginfo.eu/ceny/' },
  contact: { label: 'Оставить заявку на консультацию', url: 'https://bginfo.eu/kontakty/' },
};
