<template>
  <Page title="文件管理">
    <template #extra>
      <Space wrap class="justify-end">
        <template v-if="activeTab === 'data'">
          <Button
            v-if="canDeleteFiles"
            :disabled="selectedDedupeTargets.length === 0"
            @click="handleBatchDedupe"
          >
            批量去重
          </Button>
          <Button
            v-if="canDeleteFiles"
            danger
            :disabled="selectedFileIds.length === 0"
            @click="handleBatchDelete"
          >
            批量删除
          </Button>
          <Button v-if="canExportFiles" :loading="exporting" @click="handleExport">
            <span class="i-lucide-download" />
            导出
          </Button>
        </template>
        <template v-else-if="activeTab === 'recycle'">
          <Button
            v-if="canRecoveryFiles"
            :disabled="selectedRecycleIds.length === 0"
            @click="handleBatchRecovery"
          >
            批量恢复
          </Button>
          <Button
            v-if="canRealDeleteFiles"
            danger
            :disabled="selectedRecycleIds.length === 0"
            @click="handleBatchRealDelete"
          >
            批量彻底删除
          </Button>
        </template>
      </Space>
    </template>
    <Card class="crud-page-shell">
      <Tabs v-model:activeKey="activeTab" class="crud-page-tabs">
        <TabPane key="data" tab="数据列表">
          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="文件名称"><Input v-model:value="searchForm.origin_name" allow-clear placeholder="搜索文件名" @press-enter="loadFiles" /></SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="文件类型">
                  <Select v-model:value="searchForm.scene" allow-clear class="w-full" placeholder="请选择">
                    <SelectOption value="image">图片</SelectOption>
                    <SelectOption value="file">文件</SelectOption>
                    <SelectOption value="video">视频</SelectOption>
                  </Select>
                </SearchField>
              </Col>
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="上传通道">
                  <Select v-model:value="searchForm.driver" allow-clear class="w-full" placeholder="请选择">
                    <SelectOption
                      v-for="option in storageDriverOptions"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </SelectOption>
                  </Select>
                </SearchField>
              </Col>
              <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6">
                <Space wrap>
                  <Button type="primary" :loading="loadingFiles" @click="loadFiles">搜索</Button>
                  <Button :disabled="loadingFiles" @click="handleResetSearch">重置</Button>
                </Space>
              </Col>
            </Row>
            <CrudFilterSummary
              :items="activeFilterItems"
              empty-text="当前显示全部文件记录，可按名称、类型或上传通道快速筛选。"
            />
          </Card>

          <CrudStatCards class="mb-5" :items="summaryCards" />

          <Card class="mb-5" title="统一上传入口">
            <div v-if="canUploadFiles">
              <div class="file-upload-card-header">
                <div>
                  <div class="file-upload-card-title">上传文件到统一入口</div>
                  <div class="file-upload-card-desc">支持普通上传、秒传和按通道上传；未指定时会自动使用当前默认通道。</div>
                </div>
                <CrudToneTag color="processing" :text="`默认通道：${activeModeText}`" />
              </div>
              <Row :gutter="16" class="mb-4">
                <Col :xs="24" :md="12" :xl="8">
                  <FormItem label="本次上传通道">
                    <Select v-model:value="uploadDriver" allow-clear class="w-full" placeholder="留空则使用默认通道">
                      <SelectOption v-for="option in storageDriverOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                      </SelectOption>
                    </Select>
                  </FormItem>
                </Col>
              </Row>
              <AdminUploadField
                v-model="uploadEntryValue"
                :allow-select-existing="false"
                button-text="上传文件"
                :clearable="false"
                :driver="uploadDriver || undefined"
                :limit="20"
                :multiple="true"
                @success="handleUploadSuccess"
              />
            </div>
            <CrudNoticeAlert
              v-else
              message="当前账号没有上传权限，只能查看附件记录。"
            />
          </Card>

          <Card>
            <CrudTableHeader
              title="文件记录"
              description="展示当前有效文件记录，可查看详情、编辑备注或执行批量去重与删除。"
              :count-text="`${filePagination.total} 条记录`"
            />
            <Table
              :columns="fileColumns"
              :data-source="fileItems"
              :loading="loadingFiles"
              :locale="buildCrudTableLocale('暂无文件记录')"
              :pagination="filePagination"
              :row-selection="fileRowSelection"
              :scroll="fileTableScroll"
              row-key="id"
              @change="handleFileTableChange"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'preview'">
                  <Image
                    v-if="isImage(record)"
                    :src="record.preview_url || record.url"
                    :width="56"
                    :height="56"
                    style="object-fit: cover"
                  />
                  <video
                    v-else-if="isVideo(record)"
                    :src="record.preview_url || record.url"
                    style="height: 56px; width: 84px; object-fit: cover"
                    controls
                    preload="metadata"
                  />
                  <CrudToneTag v-else :text="record.suffix.toUpperCase() || 'FILE'" />
                </template>
                <template v-else-if="column.key === 'scene'">
                  <CrudToneTag color="processing" :text="sceneLabel(record.scene)" />
                </template>
                <template v-else-if="column.key === 'origin_name'">
                  <Tooltip :title="record.origin_name" placement="topLeft">
                    <div class="truncate">{{ record.origin_name }}</div>
                  </Tooltip>
                </template>
                <template v-else-if="column.key === 'driver'">
                  <CrudToneTag color="processing" :text="driverLabel(record.driver)" />
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="fileActions(record)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

      <TabPane v-if="canRecoveryFiles || canRealDeleteFiles" key="recycle" tab="回收站">
        <Card>
          <CrudTableHeader
            title="已删除文件"
            description="回收站内的记录可恢复到主列表；彻底删除后将不可恢复，请谨慎操作。"
            count-color="warning"
            :count-text="`${recyclePagination.total} 条记录`"
          />
          <Table
            :columns="recycleColumns"
            :data-source="recycleItems"
            :loading="loadingRecycle"
            :locale="buildCrudTableLocale('回收站为空')"
            :pagination="recyclePagination"
            :row-selection="recycleRowSelection"
            :scroll="recycleTableScroll"
            row-key="id"
            @change="handleRecycleTableChange"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.key === 'scene'">
                <CrudToneTag color="purple" :text="sceneLabel(record.scene)" />
              </template>
              <template v-else-if="column.key === 'origin_name'">
                <Tooltip :title="record.origin_name" placement="topLeft">
                  <div class="truncate">{{ record.origin_name }}</div>
                </Tooltip>
              </template>
              <template v-else-if="column.key === 'driver'">
                <CrudToneTag color="processing" :text="driverLabel(record.driver)" />
              </template>
              <template v-else-if="column.key === 'action'">
                <CrudTableActions :actions="fileRecycleActions(record)" />
              </template>
            </template>
          </Table>
        </Card>
      </TabPane>

      <TabPane v-if="canManageUploadConfig" key="extra" tab="上传通道配置">
        <Spin :spinning="loadingConfig">
          <Form class="config-page-shell" layout="vertical">
            <div class="config-section">
              <div class="config-section__header">
                <div class="config-section__title">基础设置</div>
                <div class="config-section__desc">设置默认上传通道和统一上传规则，所有存储通道都会继承这里的公共策略。</div>
              </div>
              <div class="storage-config-form">
                <FormItem label="默认上传通道" extra="未显式指定通道时，系统默认使用这里配置的上传通道。">
                  <Select v-model:value="uploadConfig.active_mode" class="w-full" placeholder="请选择默认上传通道">
                    <SelectOption v-for="option in storageDriverOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectOption>
                  </Select>
                </FormItem>
              </div>
            </div>

            <div class="config-section">
              <div class="config-section__header">
                <div class="config-section__title">公共配置</div>
                <div class="config-section__desc">用于统一控制命名、链接、上传体积和分块策略。</div>
              </div>
              <div class="storage-config-form">
                <FormItem label="命名方式" extra="推荐使用文件哈希值，可直接启用秒传和去重能力。">
                  <Select v-model:value="uploadConfig.common.name_type" class="w-full" placeholder="请选择命名方式">
                    <SelectOption value="hash">文件哈希值（支持秒传）</SelectOption>
                    <SelectOption value="date-random">日期 + 随机</SelectOption>
                  </Select>
                </FormItem>
                <FormItem label="链接类型" extra="完整链接会返回可直接访问的 URL，相对链接和文件路径适合二次拼接。">
                  <Select v-model:value="uploadConfig.common.link_type" class="w-full" placeholder="请选择链接类型">
                    <SelectOption value="relative_url">相对链接</SelectOption>
                    <SelectOption value="full_url">完整链接（可直接访问）</SelectOption>
                    <SelectOption value="storage_path">文件路径</SelectOption>
                  </Select>
                </FormItem>
                <FormItem label="允许类型" extra="多个后缀以英文逗号分隔，例如 png,jpg,rar,doc">
                  <Input v-model:value="uploadConfig.common.allow_exts" placeholder="例如 png,jpg,rar,doc" />
                </FormItem>
                <FormItem label="访问协议" extra="完整链接模式下用于生成默认访问协议，AUTO 表示协议跟随当前请求。">
                  <Select v-model:value="uploadConfig.common.protocol" class="w-full" placeholder="请选择访问协议">
                    <SelectOption value="http">HTTP</SelectOption>
                    <SelectOption value="https">HTTPS</SelectOption>
                    <SelectOption value="auto">AUTO</SelectOption>
                  </Select>
                </FormItem>
                <FormItem label="最大体积(MB)" extra="限制单个文件允许上传的最大体积。">
                  <InputNumber v-model:value="uploadConfig.common.max_size_mb" :min="1" class="w-full" placeholder="请输入最大体积" />
                </FormItem>
                <FormItem label="中转分块阈值(MB)" extra="文件大于该值时，本地中转上传会自动切换为分块传输。">
                  <InputNumber v-model:value="uploadConfig.common.chunk_threshold_mb" :min="1" class="w-full" placeholder="请输入中转分块阈值" />
                </FormItem>
                <FormItem label="直传分片阈值(MB)" extra="云端直传时，文件大于该值会优先走分片上传。">
                  <InputNumber v-model:value="uploadConfig.common.multipart_threshold_mb" :min="1" class="w-full" placeholder="请输入直传分片阈值" />
                </FormItem>
                <FormItem label="分片大小(MB)" extra="用于直传分片和中转分块的单片体积配置。">
                  <InputNumber v-model:value="uploadConfig.common.part_size_mb" :min="1" class="w-full" placeholder="请输入分片大小" />
                </FormItem>
              </div>
            </div>

            <div class="config-section">
              <div class="config-section__header">
                <div class="config-section__title">存储通道配置</div>
                <div class="config-section__desc">按照实际接入的存储通道逐项配置，字段提示会跟随通道类型展示。</div>
              </div>
              <Tabs v-model:activeKey="configDriverTab">
                <TabPane key="local" tab="本地存储">
                  <div class="storage-config-form">
                    <FormItem label="通道名称" :extra="driverFieldHelp('local', 'title')">
                      <Input
                        v-model:value="uploadConfig.drivers.local.title"
                        :placeholder="driverFieldPlaceholder('local', 'title', '本地存储')"
                      />
                    </FormItem>
                    <FormItem
                      label="存储路径"
                      :extra="localStoragePathHelp"
                    >
                      <Input
                        v-model:value="uploadConfig.drivers.local.storage_path"
                        :placeholder="driverFieldPlaceholder('local', 'storage_path', 'upload')"
                      />
                    </FormItem>
                    <FormItem label="访问域名" :extra="driverFieldHelp('local', 'domain')">
                      <Input
                        v-model:value="uploadConfig.drivers.local.domain"
                        :placeholder="driverFieldPlaceholder('local', 'domain', 'files.example.com')"
                      />
                    </FormItem>
                  </div>
                </TabPane>

                <TabPane key="alist" tab="AList">
                  <div class="storage-config-form">
                    <FormItem label="启用状态" extra="启用后即可使用 AList 作为独立上传通道。">
                      <Switch v-model:checked="uploadConfig.drivers.alist.enabled" />
                    </FormItem>
                    <FormItem label="通道名称" :extra="driverFieldHelp('alist', 'title')">
                      <Input
                        v-model:value="uploadConfig.drivers.alist.title"
                        :placeholder="driverFieldPlaceholder('alist', 'title', 'AList')"
                      />
                    </FormItem>
                    <FormItem label="服务地址" :extra="driverFieldHelp('alist', 'endpoint')">
                      <Input
                        v-model:value="uploadConfig.drivers.alist.endpoint"
                        :placeholder="driverFieldPlaceholder('alist', 'endpoint', 'http://127.0.0.1:5244')"
                      />
                    </FormItem>
                    <FormItem label="上传根目录" :extra="driverFieldHelp('alist', 'root')">
                      <Input
                        v-model:value="uploadConfig.drivers.alist.root"
                        :placeholder="driverFieldPlaceholder('alist', 'root', '/upload')"
                      />
                    </FormItem>
                    <FormItem label="公开访问前缀" :extra="driverFieldHelp('alist', 'public_path')">
                      <Input
                        v-model:value="uploadConfig.drivers.alist.public_path"
                        :placeholder="driverFieldPlaceholder('alist', 'public_path', '/d')"
                      />
                    </FormItem>
                    <FormItem label="访问域名" :extra="driverFieldHelp('alist', 'domain')">
                      <Input
                        v-model:value="uploadConfig.drivers.alist.domain"
                        :placeholder="driverFieldPlaceholder('alist', 'domain', 'storage.example.com')"
                      />
                    </FormItem>
                    <FormItem label="用户名" :extra="driverFieldHelp('alist', 'username')">
                      <Input
                        v-model:value="uploadConfig.drivers.alist.username"
                        :placeholder="driverFieldPlaceholder('alist', 'username', 'admin')"
                      />
                    </FormItem>
                    <FormItem label="密码" :extra="driverFieldHelp('alist', 'password')">
                      <InputPassword
                        v-model:value="uploadConfig.drivers.alist.password"
                        :placeholder="driverFieldPlaceholder('alist', 'password', '留空表示保留原值')"
                      />
                    </FormItem>
                  </div>
                </TabPane>

                <TabPane key="qiniu" tab="七牛云">
                  <div class="storage-config-form">
                    <FormItem label="启用状态" extra="启用后即可使用七牛云作为独立上传通道。">
                      <Switch v-model:checked="uploadConfig.drivers.qiniu.enabled" />
                    </FormItem>
                    <FormItem label="通道名称" :extra="driverFieldHelp('qiniu', 'title')">
                      <Input
                        v-model:value="uploadConfig.drivers.qiniu.title"
                        :placeholder="driverFieldPlaceholder('qiniu', 'title', '七牛云')"
                      />
                    </FormItem>
                    <FormItem label="存储区域" :extra="driverFieldHelp('qiniu', 'region')">
                      <Select v-model:value="uploadConfig.drivers.qiniu.region" class="w-full" :placeholder="driverFieldPlaceholder('qiniu', 'region', '请选择存储区域')">
                        <SelectOption
                          v-for="region in driverRegions('qiniu')"
                          :key="region.value"
                          :value="region.value"
                        >
                          {{ driverRegionLabel(region) }}
                        </SelectOption>
                      </Select>
                    </FormItem>
                    <FormItem label="空间名称" :extra="driverFieldHelp('qiniu', 'bucket')">
                      <Input
                        v-model:value="uploadConfig.drivers.qiniu.bucket"
                        :placeholder="driverFieldPlaceholder('qiniu', 'bucket', 'example-bucket')"
                      />
                    </FormItem>
                    <FormItem label="访问域名" :extra="driverFieldHelp('qiniu', 'domain')">
                      <Input
                        v-model:value="uploadConfig.drivers.qiniu.domain"
                        :placeholder="driverFieldPlaceholder('qiniu', 'domain', 'static.example.com')"
                      />
                    </FormItem>
                    <FormItem
                      label="AccessKey"
                      :extra="`${driverFieldHelp('qiniu', 'access_key')} 当前：${uploadConfig.drivers.qiniu.access_key_masked || '未设置'}`"
                    >
                      <Input
                        v-model:value="uploadConfig.drivers.qiniu.access_key"
                        :placeholder="driverFieldPlaceholder('qiniu', 'access_key', '留空表示保留原值')"
                      />
                    </FormItem>
                    <FormItem
                      label="SecretKey"
                      :extra="`${driverFieldHelp('qiniu', 'secret_key')} 当前：${uploadConfig.drivers.qiniu.secret_key_configured ? '已设置' : '未设置'}`"
                    >
                      <InputPassword
                        v-model:value="uploadConfig.drivers.qiniu.secret_key"
                        :placeholder="driverFieldPlaceholder('qiniu', 'secret_key', '留空表示保留原值')"
                      />
                    </FormItem>
                  </div>
                </TabPane>

                <TabPane key="oss" tab="阿里云 OSS">
                  <div class="storage-config-form">
                    <FormItem label="启用状态" extra="启用后即可使用阿里云 OSS 作为独立上传通道。">
                      <Switch v-model:checked="uploadConfig.drivers.oss.enabled" />
                    </FormItem>
                    <FormItem label="通道名称" :extra="driverFieldHelp('oss', 'title')">
                      <Input
                        v-model:value="uploadConfig.drivers.oss.title"
                        :placeholder="driverFieldPlaceholder('oss', 'title', '阿里云 OSS')"
                      />
                    </FormItem>
                    <FormItem label="存储区域" :extra="driverFieldHelp('oss', 'region')">
                      <Select
                        v-model:value="uploadConfig.drivers.oss.region"
                        class="w-full"
                        :placeholder="driverFieldPlaceholder('oss', 'region', '请选择存储区域')"
                        @change="handleAliyunRegionChange"
                      >
                        <SelectOption
                          v-for="region in driverRegions('oss')"
                          :key="region.value"
                          :value="region.value"
                        >
                          {{ driverRegionLabel(region) }}
                        </SelectOption>
                      </Select>
                    </FormItem>
                    <FormItem
                      label="访问端点"
                      :extra="driverFieldHelp('oss', 'endpoint')"
                    >
                      <Input
                        v-model:value="uploadConfig.drivers.oss.endpoint"
                        :placeholder="driverFieldPlaceholder('oss', 'endpoint', 'oss-cn-hangzhou.aliyuncs.com')"
                      />
                    </FormItem>
                    <FormItem label="空间名称" :extra="driverFieldHelp('oss', 'bucket')">
                      <Input
                        v-model:value="uploadConfig.drivers.oss.bucket"
                        :placeholder="driverFieldPlaceholder('oss', 'bucket', 'example-bucket')"
                      />
                    </FormItem>
                    <FormItem label="访问域名" :extra="driverFieldHelp('oss', 'domain')">
                      <Input
                        v-model:value="uploadConfig.drivers.oss.domain"
                        :placeholder="driverFieldPlaceholder('oss', 'domain', 'cdn.example.com')"
                      />
                    </FormItem>
                    <FormItem
                      label="AccessKey ID"
                      :extra="`${driverFieldHelp('oss', 'access_id')} 当前：${uploadConfig.drivers.oss.access_id_masked || '未设置'}`"
                    >
                      <Input
                        v-model:value="uploadConfig.drivers.oss.access_id"
                        :placeholder="driverFieldPlaceholder('oss', 'access_id', '留空表示保留原值')"
                      />
                    </FormItem>
                    <FormItem
                      label="AccessKey Secret"
                      :extra="`${driverFieldHelp('oss', 'access_secret')} 当前：${uploadConfig.drivers.oss.access_secret_configured ? '已设置' : '未设置'}`"
                    >
                      <InputPassword
                        v-model:value="uploadConfig.drivers.oss.access_secret"
                        :placeholder="driverFieldPlaceholder('oss', 'access_secret', '留空表示保留原值')"
                      />
                    </FormItem>
                  </div>
                </TabPane>

                <TabPane key="cos" tab="腾讯云 COS">
                  <div class="storage-config-form">
                    <FormItem label="启用状态" extra="启用后即可使用腾讯云 COS 作为独立上传通道。">
                      <Switch v-model:checked="uploadConfig.drivers.cos.enabled" />
                    </FormItem>
                    <FormItem label="通道名称" :extra="driverFieldHelp('cos', 'title')">
                      <Input
                        v-model:value="uploadConfig.drivers.cos.title"
                        :placeholder="driverFieldPlaceholder('cos', 'title', '腾讯云 COS')"
                      />
                    </FormItem>
                    <FormItem label="存储区域" :extra="driverFieldHelp('cos', 'region')">
                      <Select v-model:value="uploadConfig.drivers.cos.region" class="w-full" :placeholder="driverFieldPlaceholder('cos', 'region', '请选择存储区域')">
                        <SelectOption
                          v-for="region in driverRegions('cos')"
                          :key="region.value"
                          :value="region.value"
                        >
                          {{ driverRegionLabel(region) }}
                        </SelectOption>
                      </Select>
                    </FormItem>
                    <FormItem label="空间名称" :extra="driverFieldHelp('cos', 'bucket')">
                      <Input
                        v-model:value="uploadConfig.drivers.cos.bucket"
                        :placeholder="driverFieldPlaceholder('cos', 'bucket', 'examplebucket-1250000000')"
                      />
                    </FormItem>
                    <FormItem label="访问域名" :extra="driverFieldHelp('cos', 'domain')">
                      <Input
                        v-model:value="uploadConfig.drivers.cos.domain"
                        :placeholder="driverFieldPlaceholder('cos', 'domain', 'static.example.com')"
                      />
                    </FormItem>
                    <FormItem
                      label="SecretId"
                      :extra="`${driverFieldHelp('cos', 'secret_id')} 当前：${uploadConfig.drivers.cos.secret_id_masked || '未设置'}`"
                    >
                      <Input
                        v-model:value="uploadConfig.drivers.cos.secret_id"
                        :placeholder="driverFieldPlaceholder('cos', 'secret_id', '留空表示保留原值')"
                      />
                    </FormItem>
                    <FormItem
                      label="SecretKey"
                      :extra="`${driverFieldHelp('cos', 'secret_key')} 当前：${uploadConfig.drivers.cos.secret_key_configured ? '已设置' : '未设置'}`"
                    >
                      <InputPassword
                        v-model:value="uploadConfig.drivers.cos.secret_key"
                        :placeholder="driverFieldPlaceholder('cos', 'secret_key', '留空表示保留原值')"
                      />
                    </FormItem>
                  </div>
                </TabPane>

              </Tabs>
            </div>

            <div class="mt-5 text-right">
              <Button type="primary" :loading="savingConfig" @click="handleSaveUploadConfig">保存上传配置</Button>
            </div>
          </Form>
        </Spin>
      </TabPane>
      </Tabs>
    </Card>

    <Modal
      :open="detailOpen"
      title="文件详情"
      :width="popupWidth.lg"
      ok-text="关闭"
      @cancel="detailOpen = false"
      @ok="detailOpen = false"
    >
      <CrudDetailPanel v-if="currentFile">
        <CrudDetailHero
          icon="i-lucide-file-stack"
          :lines="[
            `MIME：${currentFile.mime_type || '-'}`,
            `存储位置：${currentFile.storage_path}/${currentFile.object_name}`,
          ]"
          :tags="[
            { color: 'processing', label: sceneLabel(currentFile.scene) },
            { color: 'processing', label: driverLabel(currentFile.driver) },
            { label: currentFile.size_info },
          ]"
          :title="currentFile.origin_name"
        >
          <template #aside>
            <div class="file-detail-preview">
              <Image
                v-if="isImage(currentFile)"
                :src="currentFile.preview_url || currentFile.url"
                :width="132"
                :height="132"
                style="object-fit: cover"
              />
              <video
                v-else-if="isVideo(currentFile)"
                :src="currentFile.preview_url || currentFile.url"
                style="height: 132px; width: 200px; border-radius: 12px; object-fit: cover"
                controls
                preload="metadata"
              />
              <div v-else class="file-detail-file-badge">
                {{ currentFile.suffix.toUpperCase() || 'FILE' }}
              </div>
            </div>
          </template>
        </CrudDetailHero>
        <div class="file-detail-links">
          <a :href="currentFile.url" rel="noreferrer" target="_blank">打开文件</a>
          <a
            v-if="currentFile.preview_url"
            :href="currentFile.preview_url"
            rel="noreferrer"
            target="_blank"
          >
            预览链接
          </a>
          <a
            v-if="currentFile.download_url"
            :href="currentFile.download_url"
            rel="noreferrer"
            target="_blank"
          >
            下载链接
          </a>
        </div>

        <CrudDetailDescriptions>
          <DescriptionsItem label="文件 ID">{{ currentFile.id }}</DescriptionsItem>
          <DescriptionsItem label="创建时间">{{ currentFile.created_at }}</DescriptionsItem>
          <DescriptionsItem label="文件名">{{ currentFile.origin_name }}</DescriptionsItem>
          <DescriptionsItem label="文件后缀">{{ currentFile.suffix || '-' }}</DescriptionsItem>
          <DescriptionsItem label="上传通道">{{ driverLabel(currentFile.driver) }}</DescriptionsItem>
          <DescriptionsItem label="文件类型">{{ sceneLabel(currentFile.scene) }}</DescriptionsItem>
          <DescriptionsItem label="文件大小">{{ currentFile.size_info }}</DescriptionsItem>
          <DescriptionsItem label="MIME">{{ currentFile.mime_type || '-' }}</DescriptionsItem>
          <DescriptionsItem label="哈希值" :span="2">{{ currentFile.hash || '-' }}</DescriptionsItem>
          <DescriptionsItem label="对象名" :span="2">{{ currentFile.object_name }}</DescriptionsItem>
          <DescriptionsItem label="存储路径" :span="2">{{ currentFile.storage_path }}/{{ currentFile.object_name }}</DescriptionsItem>
          <DescriptionsItem label="访问链接" :span="2">
            <CrudDetailLink :href="currentFile.url" copy-label="访问链接" />
          </DescriptionsItem>
          <DescriptionsItem v-if="currentFile.preview_url" label="预览链接" :span="2">
            <CrudDetailLink :href="currentFile.preview_url" copy-label="预览链接" />
          </DescriptionsItem>
          <DescriptionsItem v-if="currentFile.download_url" label="下载链接" :span="2">
            <CrudDetailLink
              :href="currentFile.download_url"
              copy-label="下载链接"
              text="打开下载链接"
            />
          </DescriptionsItem>
          <DescriptionsItem label="备注" :span="2">{{ currentFile.remark || '-' }}</DescriptionsItem>
        </CrudDetailDescriptions>
      </CrudDetailPanel>
    </Modal>

    <Drawer
      :open="editOpen"
      title="编辑文件"
      :body-style="{ padding: '20px 24px 8px' }"
      :width="popupWidth.xs"
      placement="right"
      @close="editOpen = false"
    >
      <Form layout="vertical">
        <FormItem label="原始文件名">
          <Input v-model:value="editForm.origin_name" />
        </FormItem>
        <FormItem label="备注">
          <InputTextArea v-model:value="editForm.remark" :rows="3" />
        </FormItem>
      </Form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <Button @click="editOpen = false">取消</Button>
          <Button type="primary" @click="handleSubmitEdit">确定</Button>
        </div>
      </template>
    </Drawer>
  </Page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import {
  Button,
  Card,
  Col,
  DescriptionsItem,
  Drawer,
  Form,
  FormItem,
  Image,
  Input,
  InputNumber,
  Modal,
  Row,
  Select,
  SelectOption,
  Space,
  Spin,
  Switch,
  Table,
  Tabs,
  TabPane,
  Tooltip,
  message,
} from 'ant-design-vue';

import { useAccess } from '@vben/access';

import {
  AdminUploadField,
  CrudDetailDescriptions,
  CrudDetailHero,
  CrudDetailLink,
  CrudNoticeAlert,
  CrudDetailPanel,
  CrudFilterSummary,
  buildCrudTableLocale,
  CrudStatCards,
  CrudToneTag,
  CrudTableHeader,
  Page,
  resetUploadRuntimeConfig,
} from '@vben/common-ui';

import { fileApiService, type FileApi } from '#/api';
import { exportCrudXlsx } from '#/utils/crud-excel';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';
import { popupWidth } from '#/utils/popup';
import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';

type FileRecord = FileApi.FileRecord;
type UploadDriverKey = 'alist' | 'cos' | 'local' | 'oss' | 'qiniu';

const InputPassword = Input.Password;
const InputTextArea = Input.TextArea;

const { hasAccessByCodes } = useAccess();

const canUploadFiles = computed(() => hasAccessByCodes(['system.file.upload']));
const canDeleteFiles = computed(() => hasAccessByCodes(['system.file.delete']));
const canUpdateFiles = computed(() => hasAccessByCodes(['system.file.update']));
const canRecoveryFiles = computed(() => hasAccessByCodes(['system.file.recovery']));
const canRealDeleteFiles = computed(() => hasAccessByCodes(['system.file.real-delete']));
const canManageUploadConfig = computed(() => hasAccessByCodes(['system.file.upload-config']));
const canExportFiles = computed(() => hasAccessByCodes(['system.file.export']));

const activeTab = ref('data');
const configDriverTab = ref('local');
const loadingFiles = ref(false);
const exporting = ref(false);
const loadingRecycle = ref(false);
const loadingConfig = ref(false);
const savingConfig = ref(false);
const fileItems = ref<FileRecord[]>([]);
const recycleItems = ref<FileRecord[]>([]);
const selectedFileIds = ref<number[]>([]);
const selectedFileRecords = ref<FileRecord[]>([]);
const selectedRecycleIds = ref<number[]>([]);
const currentFile = ref<FileRecord | null>(null);
const detailOpen = ref(false);
const editOpen = ref(false);
const uploadEntryValue = ref<FileApi.FileRecord[]>([]);
const uploadDriver = ref<string | undefined>(undefined);
const filePagination = ref({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const recyclePagination = ref({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const stats = ref<FileApi.FileStatistics>({
  total: 0,
  today_uploaded: 0,
  total_size_byte: 0,
  by_driver: {},
  by_scene: {},
  by_storage_mode: {},
});
const searchForm = ref({
  driver: undefined as string | undefined,
  origin_name: '',
  scene: undefined as string | undefined,
});
const editForm = ref({
  id: 0,
  origin_name: '',
  remark: '',
});

const uploadConfig = ref<FileApi.UploadConfig>(createDefaultUploadConfig());
const storageDriverOptions: Array<{ label: string; value: UploadDriverKey }> = [
  { label: '本地存储', value: 'local' },
  { label: 'AList', value: 'alist' },
  { label: '七牛云', value: 'qiniu' },
  { label: '阿里云 OSS', value: 'oss' },
  { label: '腾讯云 COS', value: 'cos' },
];

const fileActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([fileActions({})], { maxWidth: 220 }));
const recycleActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([fileRecycleActions({})], { maxWidth: 180 }));
const fileColumns = computed(() => [
  { title: '预览', key: 'preview', width: 110, fixed: 'left' as const },
  { title: '文件名', dataIndex: 'origin_name', key: 'origin_name', width: 280, ellipsis: true },
  { title: '类型', dataIndex: 'scene', key: 'scene', width: 100 },
  { title: '上传通道', dataIndex: 'driver', key: 'driver', width: 140 },
  { title: '大小', dataIndex: 'size_info', key: 'size_info', width: 120 },
  { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: fileActionColumnWidth.value, fixed: 'right' as const },
]);

const recycleColumns = computed(() => [
  { title: '文件名', dataIndex: 'origin_name', key: 'origin_name', width: 280, ellipsis: true },
  { title: '类型', dataIndex: 'scene', key: 'scene', width: 100 },
  { title: '上传通道', dataIndex: 'driver', key: 'driver', width: 140 },
  { title: '大小', dataIndex: 'size_info', key: 'size_info', width: 120 },
  { title: '删除时间', dataIndex: 'deleted_at', key: 'deleted_at', width: 180 },
  { title: '操作', key: 'action', width: recycleActionColumnWidth.value, fixed: 'right' as const },
]);

const exportColumns = [
  { key: 'id', title: 'ID', width: 80 },
  { key: 'origin_name', title: '文件名', width: 280 },
  { key: 'scene', title: '类型', width: 100, formatter: (record: FileRecord) => sceneLabel(record.scene) },
  { key: 'driver', title: '上传通道', width: 140, formatter: (record: FileRecord) => driverLabel(record.driver) },
  { key: 'size_info', title: '大小', width: 120 },
  { key: 'mime_type', title: 'MIME', width: 180 },
  { key: 'hash', title: '哈希值', width: 240 },
  { key: 'storage_path', title: '存储路径', width: 220 },
  { key: 'object_name', title: '对象名', width: 260 },
  { key: 'url', title: '访问链接', width: 360 },
  { key: 'remark', title: '备注', width: 220 },
  { key: 'created_at', title: '创建时间', width: 180 },
];

const fileTableScroll = computed(() => buildTableScrollX(fileColumns.value, { selectionWidth: 60 }));
const recycleTableScroll = computed(() => buildTableScrollX(recycleColumns.value, {
  selectionWidth: 60,
}));

function fileActions(record: FileRecord | Record<string, any>) {
  return [
    { label: '详情', onClick: () => handleView(record) },
    { label: '编辑', visible: canUpdateFiles.value, onClick: () => handleEdit(record) },
    { label: '删除', visible: canDeleteFiles.value, danger: true, onClick: () => handleDelete(record) },
  ];
}

function fileRecycleActions(record: FileRecord | Record<string, any>) {
  return [
    { label: '恢复', visible: canRecoveryFiles.value, onClick: () => handleRecovery(record) },
    { label: '彻底删除', visible: canRealDeleteFiles.value, danger: true, onClick: () => handleRealDelete(record) },
  ];
}

const fileRowSelection = computed(() => ({
  onChange: (keys: Array<number | string>, rows: FileRecord[]) => {
    selectedFileIds.value = keys.map((item) => Number(item));
    selectedFileRecords.value = rows;
  },
  selectedRowKeys: selectedFileIds.value,
}));

const recycleRowSelection = computed(() => {
  if (!canRecoveryFiles.value && !canRealDeleteFiles.value) {
    return undefined;
  }

  return {
    onChange: (keys: Array<number | string>) => {
      selectedRecycleIds.value = keys.map((item) => Number(item));
    },
    selectedRowKeys: selectedRecycleIds.value,
  };
});

const totalSizeText = computed(() => formatBytes(stats.value.total_size_byte));
const activeModeText = computed(() => configDriverLabel(uploadConfig.value.active_mode));
const summaryCards = computed(() => [
  {
    desc: '当前文件记录中的有效文件数量。',
    icon: 'i-lucide-files',
    label: '文件总数',
    value: String(stats.value.total),
  },
  {
    desc: '今天新增写入的文件记录数量。',
    icon: 'i-lucide-cloud-upload',
    label: '今日上传',
    value: String(stats.value.today_uploaded),
  },
  {
    desc: '按文件记录累计计算的总存储体积。',
    icon: 'i-lucide-hard-drive',
    label: '总容量',
    value: totalSizeText.value,
  },
  {
    desc: '未显式指定上传通道时采用的默认策略。',
    icon: 'i-lucide-waypoints',
    label: '默认通道',
    value: activeModeText.value,
  },
]);
const activeFilterItems = computed(() => {
  const items: Array<{ label: string; value: string }> = [];

  const originName = searchForm.value.origin_name.trim();
  if (originName !== '') {
    items.push({ label: '文件名', value: originName });
  }

  if (searchForm.value.scene) {
    items.push({ label: '类型', value: sceneLabel(searchForm.value.scene) });
  }

  if (searchForm.value.driver) {
    items.push({ label: '上传通道', value: configDriverLabel(searchForm.value.driver) });
  }

  return items;
});
const localAccessPathText = computed(() => {
  const storagePath = normalizeStoragePath(uploadConfig.value.drivers.local.storage_path);
  return storagePath === '' ? '/' : `/${storagePath}`;
});
const localStoragePathHelp = computed(() => {
  const baseHelp = driverFieldHelp('local', 'storage_path') || '相对 public 目录，例如 upload 或 static/uploads。';
  return `${baseHelp} 自动访问路径会按当前值计算为 ${localAccessPathText.value}。`;
});
const selectedDedupeTargets = computed<FileApi.DedupeTarget[]>(() => {
  const scopes = new Map<string, FileApi.DedupeTarget>();
  for (const record of selectedFileRecords.value) {
    const hash = String(record.hash || '').trim().toLowerCase();
    if (hash === '') {
      continue;
    }

    const driver = String(record.driver || '').trim();
    scopes.set(`${driver}|${hash}`, {
      driver: driver || undefined,
      hash,
    });
  }

  return Array.from(scopes.values());
});

async function loadFiles() {
  if (loadingFiles.value) return;
  loadingFiles.value = true;
  try {
    const response = await fileApiService.getFileList({
      ...searchForm.value,
      page: filePagination.value.current,
      pageSize: filePagination.value.pageSize,
    });
    fileItems.value = response.items;
    filePagination.value.total = response.total;
    selectedFileIds.value = [];
    selectedFileRecords.value = [];
  } finally {
    loadingFiles.value = false;
  }
}

async function loadRecycle() {
  if (!canRecoveryFiles.value && !canRealDeleteFiles.value) {
    recycleItems.value = [];
    recyclePagination.value.total = 0;
    selectedRecycleIds.value = [];
    return;
  }

  if (loadingRecycle.value) return;
  loadingRecycle.value = true;
  try {
    const response = await fileApiService.getRecycleList({
      page: recyclePagination.value.current,
      pageSize: recyclePagination.value.pageSize,
    });
    recycleItems.value = response.items;
    recyclePagination.value.total = response.total;
    selectedRecycleIds.value = [];
  } finally {
    loadingRecycle.value = false;
  }
}

async function loadStats() {
  stats.value = await fileApiService.getStatistics();
}

async function loadConfig() {
  if (!canManageUploadConfig.value) {
    return;
  }

  loadingConfig.value = true;
  try {
    uploadConfig.value = normalizeUploadConfig(await fileApiService.getUploadConfig());
  } finally {
    loadingConfig.value = false;
  }
}

function handleResetSearch() {
  searchForm.value.origin_name = '';
  searchForm.value.scene = undefined;
  searchForm.value.driver = undefined;
  filePagination.value.current = 1;
  loadFiles();
}

function handleFileTableChange(pagination: { current?: number; pageSize?: number }) {
  filePagination.value.current = pagination.current || 1;
  filePagination.value.pageSize = pagination.pageSize || 10;
  loadFiles();
}

function handleRecycleTableChange(pagination: { current?: number; pageSize?: number }) {
  recyclePagination.value.current = pagination.current || 1;
  recyclePagination.value.pageSize = pagination.pageSize || 10;
  loadRecycle();
}

function handleView(record: FileRecord | Record<string, any>) {
  currentFile.value = record as FileRecord;
  detailOpen.value = true;
}

function handleEdit(record: FileRecord | Record<string, any>) {
  editForm.value = {
    id: Number(record.id),
    origin_name: String(record.origin_name || ''),
    remark: String(record.remark || ''),
  };
  editOpen.value = true;
}

async function handleSubmitEdit() {
  await fileApiService.updateFile(editForm.value.id, {
    origin_name: editForm.value.origin_name,
    remark: editForm.value.remark,
  });
  message.success('更新成功');
  editOpen.value = false;
  await loadFiles();
}

async function handleExport() {
  if (exporting.value) return;
  exporting.value = true;
  try {
    await exportCrudXlsx<FileRecord>({
    columns: exportColumns,
    fetchPage: (page, pageSize) => fileApiService.getFileList({
      ...searchForm.value,
      page,
      pageSize,
    }),
    filename: `files_${new Date().toISOString().slice(0, 10)}.xlsx`,
    sheetName: '文件记录',
    });
  } finally {
    exporting.value = false;
  }
}

function handleDelete(record: FileRecord | Record<string, any>) {
  Modal.confirm({
    title: '确认删除该文件？',
    async onOk() {
      await fileApiService.deleteFile(Number(record.id));
      message.success('删除成功');
      await Promise.all([loadFiles(), loadRecycle(), loadStats()]);
    },
  });
}

function handleBatchDelete() {
  if (selectedFileIds.value.length === 0) {
    return;
  }

  Modal.confirm({
    title: `确认删除选中的 ${selectedFileIds.value.length} 个文件？`,
    async onOk() {
      await fileApiService.batchDeleteFiles(selectedFileIds.value);
      message.success('删除成功');
      await Promise.all([loadFiles(), loadRecycle(), loadStats()]);
    },
  });
}

function handleBatchDedupe() {
  if (selectedDedupeTargets.value.length === 0) {
    return;
  }

  Modal.confirm({
    title: `确认批量去重选中的 ${selectedDedupeTargets.value.length} 组文件？`,
    content: '会按通道和 Hash 分组，保留每组最新且可用的一条记录，其余旧记录会被清理。',
    async onOk() {
      const result = await fileApiService.batchDedupe(selectedDedupeTargets.value);
      message.success(`去重完成，已处理 ${result.group_count || selectedDedupeTargets.value.length} 组，删除 ${result.deleted_count} 条旧记录`);
      await Promise.all([loadFiles(), loadRecycle(), loadStats()]);
    },
  });
}

function handleRecovery(record: FileRecord | Record<string, any>) {
  Modal.confirm({
    title: '确认恢复该文件？',
    async onOk() {
      await fileApiService.recoveryFiles([Number(record.id)]);
      message.success('恢复成功');
      await Promise.all([loadFiles(), loadRecycle(), loadStats()]);
    },
  });
}

function handleRealDelete(record: FileRecord | Record<string, any>) {
  Modal.confirm({
    title: '确认彻底删除该文件？',
    okButtonProps: { danger: true },
    async onOk() {
      await fileApiService.realDeleteFiles([Number(record.id)]);
      message.success('彻底删除成功');
      await Promise.all([loadRecycle(), loadStats()]);
    },
  });
}

function handleBatchRecovery() {
  if (selectedRecycleIds.value.length === 0) {
    return;
  }

  Modal.confirm({
    title: `确认恢复选中的 ${selectedRecycleIds.value.length} 个文件？`,
    async onOk() {
      await fileApiService.recoveryFiles(selectedRecycleIds.value);
      message.success('恢复成功');
      await Promise.all([loadFiles(), loadRecycle(), loadStats()]);
    },
  });
}

function handleBatchRealDelete() {
  if (selectedRecycleIds.value.length === 0) {
    return;
  }

  Modal.confirm({
    title: `确认彻底删除选中的 ${selectedRecycleIds.value.length} 个文件？`,
    okButtonProps: { danger: true },
    async onOk() {
      await fileApiService.realDeleteFiles(selectedRecycleIds.value);
      message.success('彻底删除成功');
      await Promise.all([loadRecycle(), loadStats()]);
    },
  });
}

async function handleSaveUploadConfig() {
  savingConfig.value = true;
  try {
    const { active_mode, common, drivers } = JSON.parse(JSON.stringify(uploadConfig.value));
    await fileApiService.updateUploadConfig({ active_mode, common, drivers });
    resetUploadRuntimeConfig();
    message.success('上传配置保存成功');
    await loadConfig();
  } finally {
    savingConfig.value = false;
  }
}

async function handleUploadSuccess() {
  message.success('上传完成');
  await Promise.all([loadFiles(), loadStats()]);
}

function driverLabel(driver: string) {
  return {
    alist: 'AList',
    cos: '腾讯云 COS',
    local: '本地存储',
    oss: '阿里云 OSS',
    qiniu: '七牛云',
  }[driver] || driver;
}

function configDriverLabel(driver: string) {
  return storageDriverOptions.find((item) => item.value === driver)?.label || driver;
}

function driverRegionLabel(region: FileApi.UploadDriverRegion) {
  return `${region.label}(${region.value})`;
}

function driverRegions(driver: UploadDriverKey): FileApi.UploadDriverRegion[] {
  return uploadConfig.value.driver_meta?.[driver]?.regions || [];
}

function driverFieldPlaceholder(driver: UploadDriverKey, field: string, fallback = '') {
  return uploadConfig.value.driver_meta?.[driver]?.fields?.[field]?.placeholder || fallback;
}

function driverFieldHelp(driver: UploadDriverKey, field: string) {
  return uploadConfig.value.driver_meta?.[driver]?.fields?.[field]?.help || '';
}

function handleAliyunRegionChange(region: unknown) {
  const value = typeof region === 'string'
    ? region
    : typeof region === 'number'
      ? String(region)
      : '';
  const option = driverRegions('oss').find((item) => item.value === value);
  uploadConfig.value.drivers.oss.endpoint = option?.suggested_endpoint || '';
}

function normalizeStoragePath(value: unknown) {
  return String(value || '')
    .trim()
    .replace(/^[\\/]+|[\\/]+$/g, '')
    .split(/[\\/]+/)
    .filter((segment) => segment !== '' && segment !== '.' && segment !== '..')
    .join('/');
}

function sceneLabel(scene: string) {
  return {
    file: '文件',
    image: '图片',
    video: '视频',
  }[scene] || scene;
}

function isImage(record: FileRecord | Record<string, any>) {
  return String(record.mime_type || '').startsWith('image/');
}

function isVideo(record: FileRecord | Record<string, any>) {
  return String(record.mime_type || '').startsWith('video/');
}

function formatBytes(size = 0) {
  if (!size) {
    return '0 B';
  }

  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let value = size;
  let index = 0;
  while (value >= 1024 && index < units.length - 1) {
    value /= 1024;
    index += 1;
  }

  return `${value >= 10 ? value.toFixed(1) : value.toFixed(2)} ${units[index]}`;
}

function createDefaultUploadConfig(): FileApi.UploadConfig {
  return {
    active_mode: 'local',
    common: {
      allow_exts: 'doc,docx,gif,ico,jpg,jpeg,json,mp3,mp4,p12,pdf,pem,png,rar,txt,webm,webp,xls,xlsx,zip',
      chunk_threshold_mb: 20,
      link_type: 'full_url',
      max_size_mb: 1024,
      multipart_threshold_mb: 20,
      name_type: 'hash',
      part_size_mb: 5,
      protocol: 'https',
    },
    drivers: {
      local: { enabled: true, title: '本地存储', storage_path: 'upload', domain: '' },
      alist: {
        enabled: false,
        title: 'AList',
        endpoint: '',
        root: '/upload',
        domain: '',
        public_path: '/d',
        username: '',
        password: '',
        password_configured: false,
      },
      oss: {
        enabled: false,
        title: '阿里云 OSS',
        region: 'cn-hangzhou',
        endpoint: 'oss-cn-hangzhou.aliyuncs.com',
        bucket: '',
        domain: '',
        access_id: '',
        access_secret: '',
        access_id_masked: '',
        access_secret_configured: false,
      },
      qiniu: {
        enabled: false,
        title: '七牛云',
        region: 'z0',
        bucket: '',
        domain: '',
        access_key: '',
        secret_key: '',
        access_key_masked: '',
        secret_key_configured: false,
      },
      cos: {
        enabled: false,
        title: '腾讯云 COS',
        region: 'ap-beijing',
        bucket: '',
        domain: '',
        secret_id: '',
        secret_key: '',
        secret_id_masked: '',
        secret_key_configured: false,
      },
    },
    driver_meta: {
      alist: { direct_upload: false, fields: {}, multipart_upload: false, regions: [], title: 'AList' },
      cos: { direct_upload: true, fields: {}, multipart_upload: false, regions: [], title: '腾讯云 COS' },
      local: { direct_upload: false, fields: {}, multipart_upload: false, regions: [], title: '本地存储' },
      oss: { direct_upload: true, fields: {}, multipart_upload: true, regions: [], title: '阿里云 OSS' },
      qiniu: { direct_upload: true, fields: {}, multipart_upload: false, regions: [], title: '七牛云' },
    },
  };
}

function normalizeUploadConfig(config: FileApi.UploadConfig): FileApi.UploadConfig {
  const defaults = createDefaultUploadConfig();

  return {
    ...defaults,
    ...config,
    common: {
      ...defaults.common,
      ...(config.common || {}),
    },
    drivers: {
      ...defaults.drivers,
      ...(config.drivers || {}),
    },
    driver_meta: {
      ...defaults.driver_meta,
      ...(config.driver_meta || {}),
      oss: {
        ...defaults.driver_meta!.oss,
        ...(config.driver_meta?.oss || {}),
      } as FileApi.UploadDriverMeta,
      qiniu: {
        ...defaults.driver_meta!.qiniu,
        ...(config.driver_meta?.qiniu || {}),
      } as FileApi.UploadDriverMeta,
      cos: {
        ...defaults.driver_meta!.cos,
        ...(config.driver_meta?.cos || {}),
      } as FileApi.UploadDriverMeta,
      alist: {
        ...defaults.driver_meta!.alist,
        ...(config.driver_meta?.alist || {}),
      } as FileApi.UploadDriverMeta,
      local: {
        ...defaults.driver_meta!.local,
        ...(config.driver_meta?.local || {}),
      } as FileApi.UploadDriverMeta,
    },
  };
}

onMounted(async () => {
  await Promise.all([loadFiles(), loadRecycle(), loadStats(), loadConfig()]);
});
</script>

<style scoped>
.crud-page-tabs :deep(.ant-tabs-content-holder) {
  overflow: visible;
}

.config-page-shell {
  padding: 4px 0;
}

.config-section + .config-section {
  margin-top: 24px;
}

.config-section__header {
  margin-bottom: 16px;
}

.config-section__title {
  color: rgb(var(--foreground));
  font-size: 16px;
  font-weight: 600;
  line-height: 24px;
}

.config-section__desc {
  color: rgb(var(--foreground) / 0.6);
  font-size: 13px;
  line-height: 20px;
  margin-top: 4px;
}

.storage-config-form {
  max-width: 720px;
}

.storage-config-form :deep(.ant-form-item:last-child) {
  margin-bottom: 0;
}

.config-section :deep(.ant-tabs-nav) {
  margin-bottom: 20px;
}

.file-upload-card-header {
  align-items: flex-start;
  display: flex;
  gap: 16px;
  justify-content: space-between;
  margin-bottom: 16px;
}

.file-upload-card-title {
  color: rgb(var(--foreground));
  font-size: 15px;
  font-weight: 600;
  line-height: 24px;
}

.file-upload-card-desc {
  color: rgb(var(--foreground) / 0.6);
  font-size: 13px;
  line-height: 20px;
  margin-top: 4px;
}

.file-detail-preview {
  align-items: center;
  display: flex;
  justify-content: center;
}

.file-detail-file-badge {
  align-items: center;
  background: rgb(var(--foreground) / 0.08);
  border-radius: 16px;
  color: rgb(var(--foreground));
  display: flex;
  font-size: 20px;
  font-weight: 700;
  height: 132px;
  justify-content: center;
  letter-spacing: 0.08em;
  min-width: 132px;
  padding: 0 24px;
}

.file-detail-links {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-top: 4px;
}

.file-detail-links a {
  color: rgb(var(--primary));
  font-size: 13px;
}

@media (max-width: 991px) {
  .file-upload-card-header {
    align-items: stretch;
    flex-direction: column;
  }
}

@media (max-width: 767px) {
  .file-detail-preview {
    justify-content: flex-start;
  }
}
</style>
