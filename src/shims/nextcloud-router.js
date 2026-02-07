import * as router from '../../node_modules/@nextcloud/router/dist/index.mjs'

export const generateAvatarUrl = router.generateAvatarUrl
export const generateFilePath = router.generateFilePath
export const generateOcsUrl = router.generateOcsUrl
export const generateRemoteUrl = router.generateRemoteUrl
export const generateUrl = router.generateUrl
export const getAppRootUrl = router.getAppRootUrl
export const getRootUrl = router.getRootUrl
export const imagePath = router.imagePath
export const linkTo = router.linkTo

export const getBaseUrl =
  typeof router.getBaseUrl === 'function'
    ? router.getBaseUrl
    : router.getRootUrl
      ? router.getRootUrl
      : () => ''
