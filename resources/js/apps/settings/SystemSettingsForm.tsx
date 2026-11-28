import React, { useEffect, useRef, useState, type FormEvent } from "react";
import { ClipboardCheck, Shield, ToggleLeft } from "lucide-react";
import { Tabs, type TabItem } from "../../components/tabs";
import { btnPrimary } from "../../components/table/helpers/styles";
import { t } from "../../lib/i18n";

type ModuleSetting = {
  key: string;
  label: string;
  enabled: boolean;
  action: string;
};

type SavedSettings = {
  contact: Record<string, string>;
  assessment: Record<string, number>;
};

function isSavedSettings(value: unknown): value is SavedSettings {
  return value !== null && typeof value === "object"
    && "contact" in value && value.contact !== null && typeof value.contact === "object" && !Array.isArray(value.contact)
    && Object.values(value.contact).every((item) => typeof item === "string")
    && "assessment" in value && value.assessment !== null && typeof value.assessment === "object" && !Array.isArray(value.assessment)
    && Object.values(value.assessment).every((item) => typeof item === "number" && Number.isFinite(item));
}

type Props = {
  action: string;
  csrfToken: string;
  modules: ModuleSetting[];
  contact: Record<string, string>;
  assessment: Record<string, number>;
  contactFields: { key: string; label: string; email: boolean }[];
  assessmentFields: { key: string; label: string }[];
  onSaved?: (result: SavedSettings) => void;
  onModuleChanged?: (result: { module: string; enabled: boolean }) => void;
};

export function SystemSettingsForm({
  action,
  csrfToken,
  modules,
  contact,
  assessment,
  contactFields,
  assessmentFields,
  onSaved,
  onModuleChanged,
}: Props) {
  const [moduleValues, setModuleValues] = useState<Record<string, boolean>>(
    Object.fromEntries(modules.map((module) => [module.key, module.enabled]))
  );
  const [contactValues, setContactValues] = useState<Record<string, string>>(contact);
  const [assessmentValues, setAssessmentValues] = useState<Record<string, number>>(assessment);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState("features");
  const [savingModule, setSavingModule] = useState<string | null>(null);
  const moduleRequestPending = useRef(false);
  const [moduleError, setModuleError] = useState<string | null>(null);
  const [moduleStatus, setModuleStatus] = useState<string | null>(null);
  const hydratedValues = useRef({ contact, assessment });

  useEffect(() => {
    setModuleValues(Object.fromEntries(modules.map((module) => [module.key, module.enabled])));
  }, [modules]);

  useEffect(() => {
    const previous = hydratedValues.current;
    setContactValues((current) => Object.fromEntries(Object.entries(contact).map(([key, value]) => [
      key, current[key] === previous.contact[key] ? value : current[key],
    ])));
    setAssessmentValues((current) => Object.fromEntries(Object.entries(assessment).map(([key, value]) => [
      key, current[key] === previous.assessment[key] ? value : current[key],
    ])));
    hydratedValues.current = { contact, assessment };
  }, [contact, assessment]);

  const saveModule = async (module: ModuleSetting, enabled: boolean) => {
    if (moduleRequestPending.current || saving) return;
    moduleRequestPending.current = true;
    setSavingModule(module.key);
    setModuleError(null);
    setModuleStatus(null);

    try {
      const response = await fetch(module.action, {
        method: "PATCH",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({ enabled }),
      });
      if (!response.ok) {
        const body = await response.json().catch(() => null);
        setModuleError(`${module.label}: ${body?.message ?? t("Could not save.")}`);
        return;
      }
      setModuleStatus(t("System settings saved."));
      if (!onModuleChanged) {
        setModuleValues((current) => ({ ...current, [module.key]: enabled }));
        return;
      }
      const result: unknown = await response.json().catch(() => null);
      if (!result || typeof result !== "object" || !("module" in result) || result.module !== module.key
        || !("enabled" in result) || typeof result.enabled !== "boolean") {
        setModuleError(t("Changes were saved, but the page data could not be refreshed."));
        return;
      }
      const savedEnabled = result.enabled;
      setModuleValues((current) => ({ ...current, [module.key]: savedEnabled }));
      onModuleChanged({ module: module.key, enabled: savedEnabled });
    } catch {
      setModuleError(`${module.label}: ${t("Could not reach the server. Check your connection and try again.")}`);
    } finally {
      moduleRequestPending.current = false;
      setSavingModule(null);
    }
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (activeTab === "features" || saving || moduleRequestPending.current) return;
    setSaving(true);
    setError(null);
    setStatus(null);

    const payload = {
      ...contactValues,
      ...Object.fromEntries(Object.entries(assessmentValues).map(([key, value]) => [key, Number(value)])),
    };

    try {
      const response = await fetch(action, {
        method: "PUT",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const body = await response.json().catch(() => null);
        setError(body?.message ?? t("Could not save."));
        return;
      }

      if (onSaved) {
        setStatus(t("System settings saved."));
        const result: unknown = await response.json().catch(() => null);
        if (!isSavedSettings(result)) {
          setError(t("Changes were saved, but the page data could not be refreshed."));
          return;
        }
        setContactValues(result.contact);
        setAssessmentValues(result.assessment);
        onSaved(result);
      } else {
        window.location.reload();
      }
    } catch {
      setError(t("Could not save."));
    } finally {
      setSaving(false);
    }
  };

  const tabs: TabItem[] = [
    {
      value: "features",
      label: t("Features"),
      icon: ToggleLeft,
      content: (
      <section>
        <h4>{t("Modules")}</h4>
        <p>{t("Enabled modules apply to all users. Disabling a module does not delete its data.")}</p>
        {moduleError && <div className="alert alert-danger" role="alert">{moduleError}</div>}
        <div role="status" aria-live="polite">
          {savingModule ? t("Saving…") : moduleStatus}
        </div>
        <div className="mt-3">
          {modules.map((module) => (
            <div className="form-check form-switch mb-3" key={module.key}>
              <input
                className="form-check-input"
                type="checkbox"
                role="switch"
                id={`module-${module.key}`}
                checked={moduleValues[module.key] ?? false}
                disabled={saving || savingModule !== null}
                aria-busy={savingModule === module.key}
                onChange={(event) => void saveModule(module, event.target.checked)}
              />
              <label className="form-check-label" htmlFor={`module-${module.key}`}>
                {module.label}
              </label>
            </div>
          ))}
        </div>
      </section>
      ),
    },
    {
      value: "privacy",
      label: t("Privacy"),
      icon: Shield,
      content: (
      <section>
        <h4>{t("Privacy")}</h4>
        {contactFields.map((field) => (
          <div className="mb-3" key={field.key}>
            <label className="form-label" htmlFor={field.key}>{field.label}</label>
            <input
              className="form-control"
              type={field.email ? "email" : "text"}
              id={field.key}
              name={field.key}
              maxLength={255}
              value={contactValues[field.key] ?? ""}
              onChange={(event) => setContactValues((current) => ({
                ...current,
                [field.key]: event.target.value,
              }))}
            />
          </div>
        ))}
      </section>
      ),
    },
    {
      value: "assessment",
      label: t("Assessment"),
      icon: ClipboardCheck,
      content: (
      <section>
        <h4>{t("Assessment")}</h4>
        {assessmentFields.map((field) => (
          <div className="mb-3" key={field.key}>
            <label className="form-label" htmlFor={field.key}>{field.label}</label>
            <input
              className="form-control"
              type="number"
              id={field.key}
              name={field.key}
              min={1}
              step={1}
              required
              value={assessmentValues[field.key] ?? 1}
              onChange={(event) => setAssessmentValues((current) => ({
                ...current,
                [field.key]: Number(event.target.value),
              }))}
            />
          </div>
        ))}
      </section>
      ),
    },
  ];

  return (
    <form onSubmit={submit} className="space-y-6">
      {error && <div className="alert alert-danger" role="alert">{error}</div>}
      {status && <div role="status">{status}</div>}
      <Tabs items={tabs} label={t("Function settings")} value={activeTab} onValueChange={setActiveTab} />
      {activeTab !== "features" && <div className="flex justify-end gap-2 pt-2">
        <button type="submit" className={btnPrimary} disabled={saving || savingModule !== null}>
          {saving ? t("Saving…") : t("Save")}
        </button>
      </div>}
    </form>
  );
}
