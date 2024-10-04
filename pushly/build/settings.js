(()=>{"use strict";var e={n:t=>{var n=t&&t.__esModule?()=>t.default:()=>t;return e.d(n,{a:n}),n},d:(t,n)=>{for(var a in n)e.o(n,a)&&!e.o(t,a)&&Object.defineProperty(t,a,{enumerable:!0,get:n[a]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=window.React,n=window.wp.domReady;var a=e.n(n);const l=window.wp.element,i=window.wp.i18n,s=window.wp.components,o=window.wp.apiFetch;var d=e.n(o);const p=window.wp.data,r=window.wp.notices;function u(){var e;const n=(0,p.useSelect)((e=>e(r.store).getNotices()),[]),{removeNotice:a}=(0,p.useDispatch)(r.store),l=null!==(e=n?.filter((({type:e})=>"default"===e)))&&void 0!==e?e:[];return(0,t.createElement)(s.NoticeList,{notices:l,className:"components-editor-notices__snackbar",onRemove:a})}const c=()=>{const{loading:e,sdkKey:n,setSdkKey:a,apiKey:o,setApiKey:c,sendingEnabled:_,setSendingEnabled:h,autoSendEnabled:y,setAutoSendEnabled:m,initializationDisabled:b,setInitializationDisabled:g,initialInitializationDisabled:E,possiblePostTypes:v,enabledPostTypes:w,setEnabledPostTypes:S,saveSettings:f}=(()=>{const[e,t]=(0,l.useState)(!0),[n,a]=(0,l.useState)(""),[s,o]=(0,l.useState)(""),[u,c]=(0,l.useState)(""),[_,h]=(0,l.useState)(),[y,m]=(0,l.useState)(),[b]=(0,l.useState)([]),[g,E]=(0,l.useState)([]),[v,w]=(0,l.useState)(),[S,f]=(0,l.useState)(),{createSuccessNotice:P,createErrorNotice:k,removeNotice:N}=(0,p.useDispatch)(r.store);return(0,l.useEffect)((()=>{const e=d()({path:"/wp/v2/settings"}).then((e=>{var t;const n=null!==(t=e?.pushly)&&void 0!==t?t:{};o(void 0===n.sdk_key?null:n.sdk_key),c(void 0===n.api_key?null:n.api_key),h(void 0!==n.sending_enabled&&n.sending_enabled),m(void 0===n.auto_send_enabled?null:n.auto_send_enabled),E(void 0===n.enabled_post_types||0===n.enabled_post_types.length?["post"]:n.enabled_post_types),w(void 0!==n.initialization_disabled&&n.initialization_disabled),f(void 0!==n.initialization_disabled&&n.initialization_disabled),a(pushly_env.CDN_DOMAIN)})),n=d()({path:"/wp/v2/types"}).then((e=>{const t=["attachment","nav_menu_item"];Object.values(e).forEach((e=>{t.includes(e.slug)||e.slug.startsWith("wp_")||b.push(e)}))}));Promise.all([e,n]).then((()=>t(!1)))}),[]),(0,l.useEffect)((()=>{_&&null===y&&m(_)}),[_]),{loading:e,sdkKey:s,setSdkKey:o,apiKey:u,setApiKey:c,sendingEnabled:_,setSendingEnabled:h,autoSendEnabled:y,setAutoSendEnabled:m,possiblePostTypes:b,enabledPostTypes:g,setEnabledPostTypes:E,initializationDisabled:v,setInitializationDisabled:w,initialInitializationDisabled:S,saveSettings:()=>{if(_&&!u)return N("pushly"),void k("API Key must be provided when sending is enabled.",{id:"pushly",type:"default",explicitDismiss:!0});fetch(`https://${n}/domain-settings/${s}`).then((e=>{e.ok?e.json().then((e=>{d().use(d().createNonceMiddleware(pushly_env.API_NONCE)),d()({path:"/wp/v2/settings",method:"POST",data:{pushly:{domain_id:e.domain.id,sdk_key:s,api_key:u,sending_enabled:_,auto_send_enabled:y,initialization_disabled:v,enabled_post_types:g}}}).then((()=>{N("pushly"),P((0,i.__)("Settings saved","pushly"),{id:"pushly",type:"default"})})).catch((e=>{const t=e.data.details.pushly.message.replace("of pushly","").replace("pushly[","").replace("]","").replace(".","").split("_").map((e=>e.split("_").map((e=>e.charAt(0).toUpperCase()+e.slice(1))).join(" "))).join(" ").trim();N("pushly"),k(t,{id:"pushly",type:"default",explicitDismiss:!0})}))})):(N("pushly"),k((0,i.__)("The provided SDK Key is not valid","pushly"),{id:"pushly",type:"default",explicitDismiss:!0}))}))}}})();return e?null:(0,t.createElement)(t.Fragment,null,(0,t.createElement)("h1",null,"Pushly Settings"),(0,t.createElement)(u,null),(0,t.createElement)(s.Panel,null,(0,t.createElement)(s.PanelBody,{title:(0,i.__)("Configuration","pushly")},(0,t.createElement)(s.PanelRow,null,(0,t.createElement)(s.TextControl,{name:"sdkKey",label:(0,i.__)("SDK Key","pushly"),help:(0,i.__)("Enter the SDK Key associated to this Wordpress installation.","pushly"),value:n,onChange:e=>a(e.toString().trim()),type:"api-key",__next40pxDefaultSize:!0,__nextHasNoMarginBottom:!0}))),(0,t.createElement)(s.PanelBody,{title:(0,i.__)("Notifications","pushly")},(0,t.createElement)(s.PanelRow,null,(0,t.createElement)(s.ToggleControl,{name:"sendingEnabled",checked:_,help:(0,i.__)("Enable to add a new Notifications section to the post editor.","pushly"),label:(0,i.__)("Send Notifications for New Posts","pushly"),onChange:e=>h(!0===e),__nextHasNoMarginBottom:!0})),(0,t.createElement)(s.PanelRow,null,(0,t.createElement)(s.ToggleControl,{name:"autoSendEnabled",checked:_&&(y||null===y),help:(0,i.__)("Enable to automatically check the Send Notification checkbox in the post editor.","pushly"),label:(0,i.__)("Automatic Sending","pushly"),disabled:!_,onChange:e=>m(e),__nextHasNoMarginBottom:!0})),(0,t.createElement)(s.PanelRow,null,(0,t.createElement)("div",null,(0,t.createElement)(s.TextControl,{name:"apiKey",type:"password",label:(0,i.__)("API Key"),value:o,disabled:!_,onChange:e=>c(e.toString().trim()),__next40pxDefaultSize:!0,__nextHasNoMarginBottom:!0}),(0,t.createElement)("div",{class:"settings-help-text"},"Follow ",(0,t.createElement)("a",{href:"https://documentation.pushly.com/integration/web-browser-push/wordpress-plugin#step-1-create-your-wordpress-api-key",target:"_blank"}," the Pushly documentation")," to generate your API Key."))),(0,t.createElement)(s.PanelRow,null,(0,t.createElement)("div",null,(0,t.createElement)(s.FormTokenField,{label:"Enabled Post Types",value:w&&w.flatMap((e=>{const t=v.find((t=>t.slug===e));return t?`${t.name} (${t.slug})`:[]})),suggestions:v.map((e=>`${e.name} (${e.slug})`)),disabled:!_,onChange:e=>{const t=e.flatMap((e=>{const t=v.find((t=>`${t.name} (${t.slug})`===e));return t?t.slug:[]}));S(t)},__experimentalExpandOnFocus:!0,__nextHasNoMarginBottom:!0,__experimentalShowHowTo:!1}),(0,t.createElement)("div",{class:"settings-help-text"},"Select the post types that should be eligible for push notifications.")))),(0,t.createElement)(s.PanelBody,{title:(0,i.__)("Advanced","pushly"),initialOpen:E},(0,t.createElement)(s.ToggleControl,{name:"initializationDisabled",checked:b,help:(0,i.__)("When this box is checked the Pushly SDK will not automatically run on your site; Existing subscribers can still receive notifications.","pushly"),label:(0,i.__)("Disable SDK Initialization","pushly"),onChange:e=>g(e),__nextHasNoMarginBottom:!0}))),(0,t.createElement)(s.Button,{variant:"primary",onClick:f,__next40pxDefaultSize:!0},(0,i.__)("Save","pushly")))};a()((()=>{if("undefined"!=typeof pushly_env&&pushly_env?.CDN_DOMAIN&&pushly_env?.API_NONCE){const e=document.getElementById("pushly-settings");e&&(l.createRoot?(0,l.createRoot)(e).render((0,t.createElement)(c,null)):(0,l.render)((0,t.createElement)(c,null),e))}}))})();