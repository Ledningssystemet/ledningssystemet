const risks = [
  "I händelse av ett intrång i portal1.ledningssystemet.se (Adminportal klusterno0)",
  "Verksamheten har otillräcklig förmåga att ge första hjälpen",
  "Ej utförda utrymningsövningar vid verksamhetsställe Fritsla",
  "Backuplösning fungerar ej för Ledningssystemet.se Produktion",
  "I händelse av ett intrång i Ledningssystemet.se Produktion vet vi inte vad som skett",
  "Backuplösning fungerar ej för Backupserver",
  "Kända sårbarheter i Backupserver",
  "Hot riktade mot Ledningssystemet.se Produktion kommer ej till verksamhetens kännedom",
];

export default function TopRisks() {
  return (
    <div className="bg-card rounded-xl border border-border card-shine">
      <div className="p-4 pb-3">
        <div className="flex items-center justify-between">
          <h3 className="font-heading font-semibold text-card-foreground">10 högsta riskerna</h3>
        </div>
      </div>
      <div className="divide-y divide-border">
        {risks.map((risk, i) => (
          <button
            key={i}
            className="w-full flex items-start gap-3 px-4 py-2.5 text-left hover:bg-muted/50 transition-colors group"
          >
            <span className="status-dot status-dot-danger mt-1.5" />
            <span className="text-sm text-card-foreground group-hover:text-primary transition-colors leading-snug">
              {risk}
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}
