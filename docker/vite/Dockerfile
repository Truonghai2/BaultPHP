FROM node:18-alpine

# Install build tools required for compiling native Node.js modules.
# The `node:18-alpine` image is minimal and lacks these tools by default.
# They are necessary for packages like `gifsicle` (a dependency of `vite-plugin-imagemin`)
# which may need to be compiled from source if a pre-built binary is not available
# for the container's architecture. The error `autoreconf: not found` indicates this need.

WORKDIR /app

# Sao chép package.json và package-lock.json vào trước.
# Tận dụng Docker layer caching: bước này chỉ chạy lại khi các file này thay đổi.
COPY package.json package-lock.json ./

# Cài đặt dependencies. Các build tools đã được cài ở trên.
RUN npm install

# Sao chép phần còn lại của mã nguồn.
COPY . .
