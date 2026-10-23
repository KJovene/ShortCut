interface ChampProps {
  id: string
  label: string
  type?: 'text' | 'email' | 'password' | 'tel'
  autoComplete: string
  value: string
  onBlur: () => void
  onChange: (value: string) => void
  erreur?: string
  aide?: string
}

/** Champ de formulaire libellé, avec son message d'erreur relié pour les lecteurs d'écran. */
export function Champ({
  id,
  label,
  type = 'text',
  autoComplete,
  value,
  onBlur,
  onChange,
  erreur,
  aide,
}: ChampProps) {
  const descriptions = [aide && `${id}-aide`, erreur && `${id}-erreur`].filter(Boolean).join(' ')

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-bold">
        {label}
      </label>
      <input
        id={id}
        name={id}
        type={type}
        autoComplete={autoComplete}
        value={value}
        onBlur={onBlur}
        onChange={(event) => onChange(event.target.value)}
        aria-invalid={erreur ? true : undefined}
        aria-describedby={descriptions || undefined}
        className="min-h-12 w-full rounded-xl border-[1.5px] border-lin bg-creme px-4 text-[15px] transition-colors hover:border-beige focus:border-terre focus:bg-white focus:outline-none aria-invalid:border-brique-texte"
      />
      {aide && (
        <p id={`${id}-aide`} className="m-0 text-[13px] text-taupe">
          {aide}
        </p>
      )}
      {erreur && (
        <p id={`${id}-erreur`} className="m-0 text-[13px] font-semibold text-brique-texte">
          {erreur}
        </p>
      )}
    </div>
  )
}

/** Message global d'un formulaire (erreur ou confirmation). */
export function Bandeau({ ton, children }: { ton: 'erreur' | 'succes'; children: string }) {
  return ton === 'erreur' ? (
    <p
      role="alert"
      className="m-0 rounded-2xl bg-brique-fond px-4 py-3 text-sm font-semibold text-brique-texte"
    >
      {children}
    </p>
  ) : (
    <p
      role="status"
      className="m-0 rounded-2xl bg-ciel-fond px-4 py-3 text-sm font-semibold text-encre"
    >
      {children}
    </p>
  )
}

export const classeBouton =
  'flex min-h-12 w-full items-center justify-center rounded-full bg-terre px-6 text-[15px] font-extrabold text-white hover:bg-rouille disabled:opacity-60'
