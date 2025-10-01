// composables/useExternalLinks
export function useExternalLinks() {
  const registryUrl = 'https://registry.countermetrics.org/item/'
  const openCop = () => window.open('https://cop5.countermetrics.org/')
  const openCounterApi = () => window.open('https://countermetrics.stoplight.io/docs/counter-sushi-api')
  const openRegistry = () => window.open('https://registry.countermetrics.org/')
  const openRegistryApi = () => window.open('https://registry.countermetrics.org/api/v1/?format=api')
  const openGitHub = () => window.open('https://github.com/CPE-ITTeam/CC-Plus')

  const viewInRegistry = (item,registryId) => {
    if (typeof(item) == 'undefined' || typeof(registryId) == 'undefined') {
      console.warn('Missing registry argument(s) !')
      return
    }
    let _url = `${registryUrl}`+item+'/'+registryId;
    window.open(_url, '_blank');
  }

  return { openCop, openCounterApi, openRegistry, openRegistryApi, openGitHub, viewInRegistry }
}