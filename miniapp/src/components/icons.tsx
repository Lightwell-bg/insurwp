/**
 * Инлайн-иконки.
 *
 * Простые line-иконки без библиотеки: проект принципиально без внешних
 * зависимостей (см. README плагина), а набор нужен небольшой.
 */

import type { SVGProps } from 'react';

function Svg(props: SVGProps<SVGSVGElement>) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.7"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      {...props}
    />
  );
}

export function IconShield(props: SVGProps<SVGSVGElement>) {
  return (
    <Svg {...props}>
      <path d="M12 3.5l7 3v5c0 4.5-2.9 7.9-7 9-4.1-1.1-7-4.5-7-9v-5l7-3z" />
      <path d="M9 12l2 2 4-4" />
    </Svg>
  );
}

export function IconIdCard(props: SVGProps<SVGSVGElement>) {
  return (
    <Svg {...props}>
      <rect x="3" y="5.5" width="18" height="13" rx="2.2" />
      <circle cx="8.3" cy="11" r="1.8" />
      <path d="M5.5 15.7c.5-1.4 1.6-2.1 2.8-2.1s2.3.7 2.8 2.1" />
      <path d="M14 9.5h5M14 12.5h5M14 15.5h3.3" />
    </Svg>
  );
}

export function IconStar(props: SVGProps<SVGSVGElement>) {
  return (
    <Svg {...props}>
      <path d="M12 3.6l2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 15.9l-4.8 2.6.9-5.4-3.9-3.8 5.4-.8L12 3.6z" />
    </Svg>
  );
}

export function IconBriefcase(props: SVGProps<SVGSVGElement>) {
  return (
    <Svg {...props}>
      <rect x="3" y="8" width="18" height="11" rx="2" />
      <path d="M8 8V6.2C8 5 9 4 10.2 4h3.6C15 4 16 5 16 6.2V8" />
      <path d="M3 13h18" />
    </Svg>
  );
}

export function IconChevron(props: SVGProps<SVGSVGElement>) {
  return (
    <Svg {...props}>
      <path d="M9 6l6 6-6 6" />
    </Svg>
  );
}
