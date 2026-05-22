<script lang="ts" setup>
    import type { VbenFormSchema } from '@vben/common-ui'
    import { computed, markRaw } from 'vue'

    import { AuthenticationLogin, SliderCaptcha, z } from '@vben/common-ui'
    import { $t } from '@vben/locales'

    import { useAuthStore } from '#/store'

    defineOptions({ name: 'Login' })

    const authStore = useAuthStore()
    const formSchema = computed((): VbenFormSchema[] => {
        const schemas: VbenFormSchema[] = [
            {
                component: 'VbenInput',
                componentProps: {
                    placeholder: $t('authentication.usernameTip'),
                },
                fieldName: 'username',
                label: $t('authentication.username'),
                rules: z.string().min(1, { message: $t('authentication.usernameTip') }),
                defaultValue: 'admin',
            },
            {
                component: 'VbenInputPassword',
                componentProps: {
                    placeholder: $t('authentication.password'),
                },
                fieldName: 'password',
                label: $t('authentication.password'),
                rules: z.string().min(1, { message: $t('authentication.passwordTip') }),
                defaultValue: 'admin',
            },
            {
                component: markRaw(SliderCaptcha),
                fieldName: 'captcha',
                rules: z.boolean().refine((value) => value, {
                    message: $t('authentication.verifyRequiredTip'),
                }),
            },
        ]

        return schemas
    })
</script>

<template>
    <AuthenticationLogin
        :form-schema="formSchema"
        :loading="authStore.loginLoading"
        :show-code-login="false"
        :show-forget-password="false"
        :show-qrcode-login="false"
        :show-register="false"
        :show-third-party-login="false"
        @submit="authStore.authLogin"
    />
</template>
