// composables/useExternalLinks.js
export function useExternalLinks() {
  // 💡 CENTRAL BASE URL: One source of truth for your primary external link reference provider
  const REGISTRY_BASE = "https://registry.countermetrics.org";

  const LINKS = {
    // COUNTER Links
    cop: "https://cop5.countermetrics.org/",
    counterApi:
      "https://countermetrics.stoplight.io/docs/counter-sushi-api/au9uaf0yg84mo-counter-api",
    counterMetrics: "https://countermetrics.org/",
    registry: `${REGISTRY_BASE}`,
    registryApi: `${REGISTRY_BASE}/api/v1/?format=api`, // 💡 FIXED: Resolved missing slash path error context
    toolsServices: "https://www.countermetrics.org/code-of-practice/tools/",
    validator: "https://validator.countermetrics.org/",

    // Demo / Dev / Trial Links
    ccTrial: "https://kyvl.libwizard.com/f/ccplus-trial",
    demoEnv: "https://demo.ccplus.kyvl.org",
    devEnv: "https://dev.ccplus.kyvl.org",

    // Documentation Links
    ccInstall:
      "https://github.com/CPE-ITTeam/CCPLUS/blob/main/docs/installation.markdown",
    onboarding: "https://kyvl.libwizard.com/f/ccplus-local",
    kyvlGuide: "https://kyvl.org/ccplus/about",

    // Git Links
    ccLatest: "https://github.com/CPE-ITTeam/CCPLUS/releases/latest",

    // URL Finder Links
    ebscoUsage:
      "https://connect.ebsco.com/s/topic/0TO1H000000gj61WAA/ebsco-usage-consolidation?language=en_US",
    ebscoBase: "https://connect.ebsco.com/s/article/Platform-Reference-List-",
    celusInfo: "https://counter51.info",
    exLibris:
      "https://knowledge.exlibrisgroup.com/Alma/Product_Documentation/010Alma_Online_Help_(English)/020Acquisitions/030Acquisitions_Infrastructure/010Managing_Vendors/SUSHI_Vendor_Lists",
    jiscFaq: "https://jusp.jisc.ac.uk/counter-5-1-faq/",
  };

  const counterLinks = [
    {
      title: "Code of Practice",
      href: LINKS.cop,
      icon: "mdi-file-document-outline",
      divider: true,
    },
    {
      title: "COUNTER API",
      href: LINKS.counterApi,
      icon: "mdi-api",
      divider: true,
    },
    {
      title: "COUNTER Registry",
      href: LINKS.registry,
      icon: "mdi-database-outline",
    },
    {
      title: "Registry API Root",
      href: LINKS.registryApi,
      icon: "mdi-code-json",
      divider: true,
    },
    {
      title: "Tools and Services",
      href: LINKS.toolsServices,
      icon: "mdi-toolbox-outline",
    },
    {
      title: "Validator",
      href: LINKS.validator,
      icon: "mdi-check-decagram-outline",
    },
  ];

  const demoLinks = [
    { title: "Demo", href: LINKS.demoEnv, icon: "mdi-flask-outline" },
    { title: "Dev", href: LINKS.devEnv, icon: "mdi-xml" },
  ];

  const docLinks = [
    {
      title: "Installation instructions",
      href: LINKS.ccInstall,
      icon: "mdi-file-download-outline",
      divider: true,
    },
    {
      title: "Institutional onboarding",
      href: LINKS.onboarding,
      icon: "mdi-file-document-outline",
    },
    {
      title: "KYVL LibGuide (needs work)",
      href: LINKS.kyvlGuide,
      icon: "mdi-help-circle-outline",
    },
  ];

  const moreLinks = [
    {
      title: "Code of Practice",
      href: LINKS.cop,
      icon: "mdi-file-document-outline",
    },
    { title: "COUNTER API", href: LINKS.counterApi, icon: "mdi-api" },
    { title: "Registry", href: LINKS.registry, icon: "mdi-database-outline" },
    { title: "Registry API", href: LINKS.registryApi, icon: "mdi-code-json" },
  ];

  const urlFinderLinks = [
    {
      title: "EBSCO Master Platform List",
      href: LINKS.ebscoUsage,
      icon: "mdi-link-variant",
    },
    { type: "chips", divider: true }, // 👈 Special slot flag to mount the alpha chip block here
    {
      title: "counter51.info - Celus",
      href: LINKS.celusInfo,
      icon: "mdi-chart-box-outline",
    },
    {
      title: "ExLibris SUSHI Vendor Lists",
      href: LINKS.exLibris,
      icon: "mdi-clipboard-list-outline",
    },
    {
      title: "JISC Counter 5 FAQ",
      href: LINKS.jiscFaq,
      icon: "mdi-frequently-asked-questions",
    },
  ];

  return {
    LINKS,
    counterLinks,
    demoLinks,
    docLinks,
    moreLinks,
    urlFinderLinks,
  };
}
