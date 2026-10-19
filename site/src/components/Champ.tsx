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
    <div>
      <label htmlFor={id} className="block text-sm font-medium">
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
        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:outline-none"
      />
      {aide && (
        <p id={`${id}-aide`} className="mt-1 text-sm text-slate-500">
          {aide}
        </p>
      )}
      {erreur && (
        <p id={`${id}-erreur`} className="mt-1 text-sm text-red-700">
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
      className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
    >
      {children}
    </p>
  ) : (
    <p
      role="status"
      className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800"
    >
      {children}
    </p>
  )
}

export const classeBouton =
  'w-full rounded-md bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700 disabled:opacity-60'
