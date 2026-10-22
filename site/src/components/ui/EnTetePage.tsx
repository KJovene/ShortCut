import type { ReactNode } from 'react'

/** Bandeau de titre commun aux pages intérieures. */
export function EnTetePage({
  surtitre,
  titre,
  children,
  photo,
}: {
  surtitre: string
  titre: ReactNode
  children?: ReactNode
  photo?: { src: string; alt: string }
}) {
  return (
    <section className="mx-auto flex max-w-[1376px] flex-wrap items-center gap-8 px-4 pt-8 pb-6 sm:px-8 sm:pt-14 sm:pb-10 lg:gap-12">
      <div className="flex min-w-0 flex-[1_1_520px] flex-col gap-4">
        <span className="text-[13px] font-extrabold text-rouille">
          {surtitre}
        </span>
        <h1 className="m-0 font-display text-[36px] leading-[1.04] font-semibold tracking-[-1px] sm:text-[54px] sm:tracking-[-1.5px]">
          {titre}
        </h1>
        {children && (
          <div className="text-[17px] leading-relaxed text-taupe sm:text-lg">{children}</div>
        )}
      </div>
      {photo && (
        <div className="min-w-0 flex-[1_1_380px]">
          <img
            src={photo.src}
            alt={photo.alt}
            className="block h-[240px] w-full rounded-[28px] object-cover shadow-[0_20px_50px_rgba(22,35,63,0.14)] sm:h-[360px]"
          />
        </div>
      )}
    </section>
  )
}
